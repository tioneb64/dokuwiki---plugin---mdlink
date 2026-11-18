# Mdlink : liens à la Obsidian

Plugin DokuWiki (`mdlink`).

Fait comprendre à DokuWiki les liens tels qu'on les écrit dans **Obsidian**, y compris en mode Markdown (qui n'analyse pas `[[ ]]`).

## Fonctions

| Écriture | Résultat |
| --- | --- |
| `[texte](Note.md)` | lien vers la page correspondant au fichier `Note.md` |
| `[[Note]]`, `[[Note\|texte]]`, `[[Note#Titre]]`, `[[#Titre]]` | liens vers une note ou une section |
| `![[image.png]]`, `![[image.png\|300x200]]` | image (largeur, hauteur, texte alternatif) |
| `![[Note]]`, `![[Note#Titre]]` | contenu d'une note ou d'une section intégré à la page (3 niveaux maximum, droits de lecture respectés) |

La page est retrouvée d'après son nom ; si plusieurs pages portent le même nom, celle du dossier le plus proche est choisie.

## Dépendances

Aucune.

## Installation

Copier le contenu de ce dépôt dans `lib/plugins/mdlink/` (le fichier `plugin.info.txt` doit se trouver à la racine de ce dossier), puis vider le cache en rendant la date de `conf/local.php` plus récente (`touch conf/local.php`).

Avec le gestionnaire d'extensions de DokuWiki : *Administration → Gestionnaire d'extensions → Installer*, en indiquant l'adresse du dépôt (archive ZIP).

## Licence

Aucune licence n'a encore été choisie : à ajouter (fichier `LICENSE`) avant de publier le dépôt. DokuWiki est sous GPL-2.0.
