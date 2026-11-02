<?php

namespace App\Support;

use Illuminate\Support\Str;

class ChatResponder
{
    public static function reply(string $message): string
    {
        $text = Str::ascii(mb_strtolower(trim($message)));

        if ($text === '') {
            return 'Écrivez-moi ce que vous cherchez : un compte, un devis, une facture, un projet ou la vision de DOUKOU.';
        }

        $rules = [
            ['bonjour|salut|hello|bonsoir', 'Bonjour. Je suis DOUKOU. Je peux vous expliquer le compte, les devis, les factures, le suivi de projet ou la vision de la plateforme — même sans inscription.'],
            ['vision|promesse|pourquoi', 'DOUKOU veut dire suivre, en nzebi. Le logiciel tient le fil d’un dossier, de la première proposition jusqu’au solde. Chaque entreprise a son espace : devis, factures, personnel, budget et planning.'],
            ['compte|inscri|personne|entreprise|espace client', 'Deux entrées : un compte personne, ou un compte entreprise. Dans l’espace, vous créez ensuite autant d’entreprises que nécessaire. Chacune porte ses clients, ses pièces et ses projets.'],
            ['devis|modele|modèle|nomade|chantier', 'Cinq modèles de devis : Nomade, Ligne, Canopée, Soleil et Chantier. Au moment de générer la pièce, vous choisissez celui qui doit sortir.'],
            ['facture|cedre|cèdre|artisan|csmart|iris', 'Cinq modèles de facture : Cèdre, Artisans, Csmart, Atelier et Iris. Le choix se fait dans l’atelier de facture, avant l’impression.'],
            ['gantt|planning|tache|tâche|personnel', 'Sur un projet, indiquez le début, la fin visée et les tâches par personne. DOUKOU dessine un diagramme de Gantt : barres, avancement et ligne du jour.'],
            ['budget|depense|dépense|stat', 'Le budget du projet se découpe en postes prévus et dépensés. Les statistiques de l’espace résument devis, factures, encaissements et projets vivants.'],
            ['propos|doukou|oree|orée|qui|nzebi', 'DOUKOU veut dire suivre, en nzebi. C’est le logiciel des indépendants et des entreprises qui veulent des pièces claires et un suivi de mission sans tableur dispersé.'],
            ['prix|tarif|cout|coût|gratuit', 'L’espace de démonstration est ouvert. Les comptes Aurore, Marc et Léa partagent le mot de passe indiqué sur la page de connexion.'],
        ];

        foreach ($rules as [$needles, $answer]) {
            foreach (explode('|', $needles) as $needle) {
                if (str_contains($text, $needle)) {
                    return $answer;
                }
            }
        }

        return 'Je peux vous guider sans compte. Demandez-moi la vision, l’inscription, les cinq modèles de devis ou de facture, le Gantt, le budget ou les statistiques.';
    }
}
