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
            ['bonjour|salut|hello|bonsoir|bjr', 'Bonjour, je suis l’assistant de DOUKOU. Je peux vous parler de la vision, de l’inscription, des modèles de devis et de facture, du Gantt, du budget ou de l’espace client — sans créer de compte.'],
            ['vision|promesse|pourquoi|objectif', 'DOUKOU veut dire « suivre » en nzebi. L’idée : un seul outil pour suivre un dossier client du premier devis au dernier paiement, au lieu de fichiers Word et Excel dispersés.'],
            ['inscri|creer un compte|créer un compte|compte personne|compte entreprise|ouvrir un compte', 'Deux types de compte : personne ou entreprise. Avec un compte personne, vous créez ensuite votre ou vos entreprises. Chaque entreprise a ses clients, ses devis, ses factures, son personnel et ses projets. L’inscription est gratuite pendant la phase de test.'],
            ['plusieurs entreprises|changer d.entreprise|entreprises', 'Dans l’espace, la page Entreprises permet d’en créer d’autres. Le sélecteur en haut de l’écran change l’entreprise active : tout le reste (clients, pièces, projets) suit cette entreprise.'],
            ['devis|nomade|ligne|canopee|soleil|chantier', 'Cinq modèles de devis : Nomade, Ligne, Canopée, Soleil et Chantier. Au moment de générer le PDF, DOUKOU affiche les cinq avec vos vraies données et vous choisissez celui qui sort. Le client peut ensuite accepter ou refuser le devis en ligne.'],
            ['facture|cedre|artisan|csmart|atelier|iris|paiement|reglement', 'Cinq modèles de facture : Cèdre, Artisans, Csmart, Atelier et Iris. La facture se génère depuis un devis accepté, sans ressaisie, et chaque paiement met à jour le reste à payer.'],
            ['modele|template', 'Dix modèles en tout : Nomade, Ligne, Canopée, Soleil, Chantier pour les devis ; Cèdre, Artisans, Csmart, Atelier, Iris pour les factures. Le choix se fait au moment de générer le document.'],
            ['gantt|planning|tache|delai|calendrier', 'Sur une fiche projet, renseignez le début, la fin estimée et les tâches par personne : DOUKOU dessine le Gantt avec les jalons et la ligne du jour. On déplace ou allonge une tâche directement à la souris, et la fin du projet est projetée au rythme actuel.'],
            ['budget|depense|cout du projet', 'Chaque projet a ses postes de budget, prévus et dépensés. La fiche montre le budget consommé, la part facturée et ce qui reste à encaisser.'],
            ['stat|indicateur|tableau de bord|kpi', 'Le tableau de bord donne le facturé et l’encaissé du mois, les devis en attente, les factures en retard et les projets en cours. Chaque projet a aussi sa propre lecture : santé du planning, charge de l’équipe, alertes.'],
            ['personnel|equipe|employe|role', 'Le personnel de l’entreprise reçoit les tâches des projets. Les comptes de l’équipe ont un rôle : administrateur, commercial, comptable ou chef de projet. Vos clients peuvent aussi avoir un accès.'],
            ['client|espace client|portail', 'Vos clients peuvent avoir un compte : ils consultent leurs devis, leurs factures et l’avancement de leurs projets, et acceptent un devis en ligne.'],
            ['prix|tarif|cout|gratuit|payant', 'La plateforme est gratuite pendant la phase de test. Vous pouvez aussi essayer la démonstration : un compte par rôle, mot de passe Cedre2026!.'],
            ['demo|essayer|tester', 'La démonstration est ouverte : cinq comptes (administrateur, commercial, comptable, chef de projet, client), tous avec le mot de passe Cedre2026!. La liste est sur la page Démo.'],
            ['contact|joindre|telephone|mail|aide|support', 'Vous pouvez nous écrire depuis la page Contact du site. Nous répondons par e-mail.'],
            ['propos|qui etes|nzebi|doukou', 'DOUKOU veut dire « suivre » en nzebi. C’est une plateforme de devis, de factures et de suivi de projets, réalisée à Libreville.'],
        ];

        foreach ($rules as [$needles, $answer]) {
            foreach (explode('|', $needles) as $needle) {
                if (str_contains($text, $needle)) {
                    return $answer;
                }
            }
        }

        return 'Je peux vous guider sans compte. Demandez-moi la vision, l’inscription, les modèles de devis ou de facture, le Gantt, le budget, l’espace client ou la démonstration.';
    }
}
