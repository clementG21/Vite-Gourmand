<?php
declare(strict_types=1);

const FRAIS_LIVRAISON_BASE = 5.00;     // hors Bordeaux
const FRAIS_PAR_KM         = 0.59;
const SEUIL_REDUCTION      = 5;        // personnes au-dessus du minimum
const TAUX_REDUCTION       = 0.10;

function normaliser_ville(string $ville): string {
    $v = mb_strtolower(trim($ville), 'UTF-8');
    return strtr($v, ['é'=>'e','è'=>'e','ê'=>'e','à'=>'a','â'=>'a','î'=>'i','ï'=>'i','ô'=>'o','û'=>'u','ù'=>'u','ç'=>'c','-'=>' ']);
}
function est_bordeaux(string $ville): bool {
    return normaliser_ville($ville) === 'bordeaux';
}

// Règles de gestion du prix. Calcul TOUJOURS refait côté serveur (le JS n'est qu'un aperçu).
function calculer_prix(float $prixMinimum, int $nbMin, int $nb, string $ville, float $km): array {
    $unitaire  = $prixMinimum / $nbMin;
    $brut      = round($unitaire * $nb, 2);
    $reduction = ($nb >= $nbMin + SEUIL_REDUCTION) ? round($brut * TAUX_REDUCTION, 2) : 0.0;
    $livraison = est_bordeaux($ville) ? 0.0 : round(FRAIS_LIVRAISON_BASE + FRAIS_PAR_KM * max(0.0, $km), 2);
    return [
        'prix_brut'      => $brut,
        'reduction'      => $reduction,
        'prix_menu'      => round($brut - $reduction, 2),
        'prix_livraison' => $livraison,
        'total'          => round($brut - $reduction + $livraison, 2),
    ];
}

function statut_label(string $s): string {
    return [
        'en_attente' => 'En attente de validation',
        'accepte' => 'Acceptée',
        'en_preparation' => 'En préparation',
        'en_cours_livraison' => 'En cours de livraison',
        'livre' => 'Livrée',
        'attente_retour_materiel' => 'En attente du retour de matériel',
        'terminee' => 'Terminée',
        'annulee' => 'Annulée',
    ][$s] ?? $s;
}

function eur(float $v): string {
    return number_format($v, 2, ',', ' ') . ' €';
}
