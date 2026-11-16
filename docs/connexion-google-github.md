# Inscription et connexion par Google et GitHub

DOUKOU propose trois façons de créer un compte ou de se connecter : le formulaire,
Google et GitHub (Laravel Socialite). Tant que les identifiants ne sont pas renseignés,
les boutons Google / GitHub restent grisés et le formulaire fonctionne normalement.

## 1. Créer les applications chez les fournisseurs

**Google** — https://console.cloud.google.com/apis/credentials
1. Créer un « ID client OAuth » de type *Application Web*.
2. URI de redirection autorisée : `http://127.0.0.1:8010/api/auth/google/callback`
   (en production : `https://VOTRE-API/api/auth/google/callback`).

**GitHub** — https://github.com/settings/developers → *New OAuth App*
1. Homepage URL : l'adresse du site (ex. `http://127.0.0.1:5173`).
2. Authorization callback URL : `http://127.0.0.1:8010/api/auth/github/callback`.

## 2. Renseigner le fichier `.env` de l'API

```
OAUTH_CALLBACK_BASE=http://127.0.0.1:8010
FRONTEND_URL=http://127.0.0.1:5173
GOOGLE_CLIENT_ID=...
GOOGLE_CLIENT_SECRET=...
GITHUB_CLIENT_ID=...
GITHUB_CLIENT_SECRET=...
```

Puis `php artisan config:clear`. Les boutons deviennent actifs immédiatement.

## 3. Fonctionnement

1. Le bouton mène à `GET /api/auth/{google|github}/redirect`, qui envoie vers le fournisseur
   avec un jeton anti-falsification (`state`) gardé 10 minutes en cache.
2. Au retour (`/callback`), le `state` est vérifié, puis le compte est retrouvé
   (lien Google/GitHub déjà connu, ou même adresse e-mail) ou créé :
   nouveau compte « personne », rôle dirigeant, qui crée ensuite sa première entreprise.
3. L'API renvoie vers `FRONTEND_URL/connexion-sociale#code=…` : un code à usage unique
   (60 s) que le site échange contre la session (`POST /api/auth/social/exchange`).
   Le jeton de session n'apparaît jamais dans une adresse.

Les liens sont enregistrés dans la table `social_accounts` (un utilisateur peut lier Google et GitHub).
