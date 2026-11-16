{{-- Guide utilisateur DOUKOU. Produit uniquement par le serveur. --}}
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; color: #1c1a16; font-size: 11.5px; line-height: 1.45; }
        h1 { font-size: 22px; margin: 0 0 4px; color: #143728; }
        h2 { font-size: 14px; margin: 18px 0 6px; color: #1e6b45; }
        p { margin: 0 0 8px; }
        ol { margin: 0 0 8px; padding-left: 18px; }
        li { margin: 0 0 4px; }
        .chapo { color: #5c564c; margin-bottom: 12px; }
        .encart { background: #f4f7f2; border-left: 3px solid #1e6b45; padding: 8px 10px; margin-top: 8px; }
        .pied { margin-top: 22px; font-size: 10px; color: #6d675c; }
    </style>
</head>
<body>
    <h1>Guide utilisateur DOUKOU</h1>
    <p class="chapo">Suivre un dossier de bout en bout : le client, le devis, la facture et le projet. DOUKOU veut dire « suivre » en nzebi.</p>

    <h2>1. Le parcours</h2>
    <ol>
        <li><strong>Référentiel.</strong> Renseignez la fiche de l’entreprise, les sociétés, les rôles du personnel, les villes, les statuts, les formes juridiques et les pays.</li>
        <li><strong>Personnel.</strong> Ajoutez les personnes à qui les tâches des projets seront confiées. Le rôle se choisit parmi ceux du référentiel.</li>
        <li><strong>Client.</strong> Ouvrez une fiche entreprise (rattachée à une société) ou une fiche particulier.</li>
        <li><strong>Devis.</strong> Rédigez-le, enregistrez-le, générez le PDF, puis marquez-le envoyé. Le client accepte ou refuse.</li>
        <li><strong>Facture.</strong> Le comptable transforme le devis accepté en facture, l’émet, puis enregistre les paiements.</li>
        <li><strong>Projet.</strong> Posez les jalons et les tâches datées, puis publiez l’avancement. Le client le voit dans son espace.</li>
    </ol>

    <h2>2. Les devis</h2>
    <ol>
        <li>Ouvrez Devis, puis Nouveau devis. Le client, l’intitulé, la date d’émission et au moins une ligne (désignation, quantité supérieure à zéro, prix) sont obligatoires.</li>
        <li>Enregistrez. Le devis reste en brouillon tant qu’il n’est pas envoyé.</li>
        <li>Générez le PDF depuis l’écran du devis. Le fichier est préparé par le serveur.</li>
        <li>Marquez le devis « Envoyé » dès qu’il part chez le client.</li>
        <li>Le client accepte ou refuse dans son espace. Le responsable commercial peut aussi enregistrer la décision.</li>
        <li>Seul un devis accepté, qui n’a pas encore de facture, peut être transformé en facture. Un devis refusé ne se facture pas.</li>
    </ol>

    <h2>3. Les factures</h2>
    <ol>
        <li>Le comptable, l’administrateur ou le dirigeant ouvre le devis accepté et choisit « Créer la facture ». Une facture peut aussi être créée directement.</li>
        <li>Vérifiez les lignes, puis émettez la facture. Tant qu’elle est en brouillon, le client ne la voit pas.</li>
        <li>Générez le PDF et transmettez-le au client.</li>
        <li>Enregistrez chaque paiement le jour où il arrive : montant, date, mode et référence.</li>
        <li>Le statut passe à « Partielle » s’il reste un solde, puis à « Payée » quand le solde est nul.</li>
    </ol>

    <h2>4. Les projets</h2>
    <ol>
        <li>Ouvrez Projets, puis Nouveau projet. Indiquez le client, le nom, le budget et les dates.</li>
        <li>Trois jalons sont proposés : Cadrage, Production, Livraison. Ajoutez-en d’autres si le dossier le demande.</li>
        <li>Ajoutez les tâches avec une personne, une date de début et une date de fin. Sans ces dates, la tâche n’apparaît pas sur le diagramme de Gantt.</li>
        <li>Ajoutez les postes du budget (prévu et dépensé) si vous suivez la consommation.</li>
        <li>Cliquez sur « Enregistrer le projet » : les jalons, les tâches et les postes ne sont conservés qu’à ce moment-là.</li>
        <li>Publiez une mise à jour : pourcentage, état et une phrase de commentaire. Le client la voit dans son espace, datée.</li>
    </ol>

    <h2>5. Qui fait quoi</h2>
    <div class="encart">
        <p>L’administrateur et le dirigeant peuvent tout modifier. Les autres rôles lisent l’ensemble, et n’écrivent que dans leur module.</p>
        <p>Le responsable commercial gère les clients, les devis et le référentiel. Le comptable gère les factures et transforme un devis accepté en facture. Le chef de projet gère les projets et le personnel.</p>
        <p>Le client ne voit que ses propres devis déjà envoyés, ses factures émises et l’avancement de ses projets.</p>
    </div>

    <p class="pied">DOUKOU — guide utilisateur. Chaque entreprise ne voit que ses propres données.</p>
</body>
</html>
