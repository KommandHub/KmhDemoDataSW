# 0.9.0-beta.1

Première version.

**Catalogue.** `bin/console kmh:demo-data:seed` crée 2 000 produits répartis
uniformément sur 40 catégories dans cinq canaux de vente — une boutique
généraliste, un marché alimentaire, un canal B2B, une boutique santé-beauté et
un outlet. Chaque canal possède sa catégorie racine, son arborescence, sa page
d'accueil et ses produits. `--per-category` ajuste la taille ; l'augmenter ne
fait qu'ajouter des produits. Les listings de catégories utilisent la mise en
page Shopware avec barre latérale de filtres, pour qu'un catalogue de cette
taille reste navigable plutôt qu'une grille sans fin.

**Produits.** Descriptions, métadonnées SEO, prix et prix barrés, prix d'achat,
stock, EAN, références fabricant, propriétés, tags, rattachements aux catégories
et visibilité par canal. Environ 40 % sont des produits à variantes avec
configurateur fonctionnel.

**Photographies.** Trois images par produit, adaptées à sa propre catégorie —
environ 390 photographies distinctes. Le plugin embarque des identifiants de
photos plutôt que les photos elles-mêmes et les télécharge au premier
lancement ; sans accès réseau sortant, le catalogue reste complet grâce aux
images fournies.

**Tarification.** Chaque produit et chaque variante dispose de prix dégressifs,
propres à chaque canal via une règle générée par canal. Chaque canal a son
facteur de prix et son barème : le canal B2B dégresse fortement selon la
quantité, l'outlet très peu. Les règles sont créées avec la priorité la plus
basse, afin que les règles existantes priment toujours.

**Avis et ventes croisées.** Un nombre variable d'avis par produit, avec une
distribution de notes réaliste, un texte cohérent avec la note, une part en
attente de modération et une part avec réponse de la boutique. Les onglets
« Produits similaires » et « Les clients ont aussi acheté » puisent dans des
ensembles distincts et ne sortent jamais du canal de vente du produit.

**Clients et commandes.** 40 clients et 120 commandes par canal, répartis sur un
an avec un mélange réaliste de statuts de commande, de paiement et de livraison,
pour que le tableau de bord et la liste des commandes aient de quoi montrer. Le
canal B2B dispose d'un groupe de clients en prix nets et de comptes
professionnels. Deux comptes par canal sont connectables ; la commande affiche
les adresses et le mot de passe. `--skip-orders` ne crée que le catalogue.

**Pages de la boutique.** Une navigation de pied de page partagée par tous les
canaux, afin que les pages légales et de service soient maintenues une seule
fois, ainsi qu'un menu de service listant tous les canaux accessibles. Chaque
page de pied de page a du contenu ; les pages légales et contractuelles
indiquent clairement qu'il s'agit de textes de démonstration à remplacer avant
la mise en production.

**Exécutable autant de fois que nécessaire.** Les données existantes sont
inspectées avant toute écriture : canaux, catégories, fabricants, groupes de
propriétés, tags et produits correspondants sont réutilisés plutôt que
dupliqués, et complétés s'ils sont incomplets. Une seconde exécution sur une
installation inchangée ne crée rien et le signale. Les produits que le plugin
n'a pas créés ne reçoivent jamais d'avis, de prix ni de ventes croisées
générés. Rien n'est jamais supprimé, y compris à la désinstallation.

`bin/console kmh:demo-data:status` indique ce qui existe déjà sans rien écrire.
