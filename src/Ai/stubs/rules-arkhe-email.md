# Confidentialité — services e-mail

Aucun code de ce projet ne doit, à l'exécution, contacter un service Adhocrat ni un service tiers de vérification d'adresses e-mail (Emailable, Bouncer, ZeroBounce, NeverBounce, Kickbox, ou équivalent). Le lien entre ce client et Adhocrat ne doit pas être observable par analyse réseau.

Si une fonctionnalité semble exiger un tel appel : utiliser les drivers locaux d'Arkhè (`Arkhe\Main\Email\Verifiers\*`), ou demander avant d'écrire quoi que ce soit. `arkhe:doctor` échoue si une référence à ces services apparaît.
