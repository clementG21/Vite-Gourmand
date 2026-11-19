<?php

declare(strict_types=1);
// Statistiques de commandes stockées dans MongoDB (base NoSQL exigée par l'énoncé).
// Un document par commande, dénormalisé : lecture rapide pour les graphiques sans jointure SQL.
require_once __DIR__ . '/db.php';
// Dépendances Composer (mongodb/mongodb) : si elles ne sont pas installées, le site continue de
// fonctionner sans statistiques (les appels ci-dessous lèvent une exception, interceptée par les appelants).
if (is_file(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
}

use MongoDB\BSON\UTCDateTime;
use MongoDB\Client;
use MongoDB\Collection;

function mongo_commandes(): Collection
{
    static $col = null;
    if ($col === null) {
        if (!class_exists(Client::class)) {
            throw new RuntimeException('MongoDB indisponible : lancer "composer install" et activer l\'extension PHP mongodb.');
        }
        $c = config()['mongo'];
        $col = (new Client($c['uri']))->selectCollection($c['db'], 'commandes_stats');
    }
    return $col;
}

// Les écritures Mongo ne doivent jamais bloquer une commande : on journalise l'erreur et on continue.
function stats_enregistrer(int $commandeId, int $menuId, string $menuTitre, int $nb, float $montant, ?int $timestamp = null): void
{
    try {
        mongo_commandes()->insertOne([
            'commande_id'     => $commandeId,
            'menu_id'         => $menuId,
            'menu_titre'      => $menuTitre,
            'nombre_personne' => $nb,
            'montant'         => $montant,           // prix du menu après réduction (hors livraison)
            'statut'          => 'active',
            'date_commande'   => new UTCDateTime(($timestamp ?? time()) * 1000),
        ]);
    } catch (Throwable $e) {
        error_log('MongoDB insert : ' . $e->getMessage());
    }
}

function stats_maj(int $commandeId, array $champs): void
{
    try {
        mongo_commandes()->updateOne(['commande_id' => $commandeId], ['$set' => $champs]);
    } catch (Throwable $e) {
        error_log('MongoDB update : ' . $e->getMessage());
    }
}

// Nombre de commandes et chiffre d'affaires par menu, filtrables par menu et par période (date de commande).
function stats_par_menu(?int $menuId, ?string $du, ?string $au): array
{
    $match = ['statut' => ['$ne' => 'annulee']];
    if ($menuId) $match['menu_id'] = $menuId;
    $dates = [];
    if ($du && ($d = DateTime::createFromFormat('Y-m-d', $du))) $dates['$gte'] = new UTCDateTime($d->setTime(0, 0)->getTimestamp() * 1000);
    if ($au && ($d = DateTime::createFromFormat('Y-m-d', $au))) $dates['$lte'] = new UTCDateTime($d->setTime(23, 59, 59)->getTimestamp() * 1000);
    if ($dates) $match['date_commande'] = $dates;

    $cursor = mongo_commandes()->aggregate([
        ['$match' => $match],
        ['$group' => [
            '_id'              => '$menu_id',
            'titre'            => ['$first' => '$menu_titre'],
            'nombre_commandes' => ['$sum' => 1],
            'chiffre_affaires' => ['$sum' => '$montant'],
        ]],
        ['$sort' => ['titre' => 1]],
    ]);
    $lignes = [];
    foreach ($cursor as $d) {
        $lignes[] = [
            'menu_id'          => (int)$d['_id'],
            'titre'            => (string)$d['titre'],
            'nombre_commandes' => (int)$d['nombre_commandes'],
            'chiffre_affaires' => round((float)$d['chiffre_affaires'], 2),
        ];
    }
    return $lignes;
}
