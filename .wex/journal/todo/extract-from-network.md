# Médiathèque : symfony-media, -ds, -demo

Opened: 2026-10-08
Updated: 2026-10-08
Author: agent:main

## Avancement

Fait et vérifié dans un navigateur (MOJOE/local/design-system, 2026-10-08) :
dossier créé, trois fichiers envoyés dedans, vignettes webp, fil d'Ariane par
noms ; dans le formulaire de la démo, médiathèque ouverte en modale, une
couverture et deux pièces jointes choisies, article enregistré et relu.

- symfony-media : entités, stockage, miniatures GD webp, voter, `/_media/{id}`,
  API de la bibliothèque (14 tests).
- symfony-media-ds : page `/media/`, upload via `UploadCompletedEvent`,
  `MediaPickerType` en modale (6 tests).
- symfony-media-demo : article avec couverture et pièces jointes.
- symfony-design-system : explorateur (vignettes, sélection simple, noms de
  dossiers, slot d'actions), `UploadCompletedEvent`.

## Reste

- Déplacer (glisser-déposer ou action « déplacer vers ») : l'API `move` existe,
  l'écran ne l'offre pas encore.
- Recadrage d'image à l'envoi (issue #103).
- Filtre de types dans le sélecteur (n'offrir que des images pour une
  couverture) : `UploadRules` côté envoi, un filtre `mimeType` côté liste.
- Le bouton « Choisir » de la modale tombe sous la ligne de flottaison sur un
  petit écran : le passer dans le pied de la modale (`actions` de showInTarget).
- Loader : les traductions d'un template ne vont qu'à sa première instance sur
  la page ; contourné ici en passant les libellés en props, à corriger à la
  source dans symfony-loader.

## Décidé avec le owner

- Une médiathèque, pas des pièces jointes : un média existe pour lui-même, une
  entité y fait référence (table relationnelle) ; une pièce jointe n'est qu'un
  média utilisé une fois.
- **symfony-media** : le média, son stockage (en s'appuyant sur symfony-file), ses
  miniatures, les liens vers les entités, le téléchargement derrière un contrôle
  d'accès.
- **symfony-media-ds** :
  - la page bibliothèque, sur la base de l'explorateur de
    `/en/design-system/generic/data/files` (composant `file-explorer`) ; le owner
    redoute du code trop sur mesure (pas de toolbar réutilisée…) : vérifier après
    sa revue ;
  - un champ « médiathèque » sur le modèle du champ de recherche d'entité
    (entity search, ex. choisir un client dans un formulaire de facture) : il
    ouvre une modale ou un panel, l'explorateur en plusieurs modes dont vignettes,
    et permet de choisir un ou plusieurs fichiers ;
  - l'envoi réutilise dropzone, l'upload par morceaux (`ChunkedUploadReceiver`)
    et les règles d'upload existantes ; recadrage d'image (issue #103).
- **symfony-media-demo** : une bibliothèque de démo et une entité qui utilise un
  média (image de couverture).

## Sources legacy

`NETWORK/archeo/proposed-packages/symfony-media/todo/extract-from-network.md`
(Media, EntityFileService, ThumbnailService ; issues #210 PDF de facture comme
média, #103 upload d'image, #96 PDF trop gros, #118 img + object-fit).
Lecture seule : network tourne sur des données de prod.
