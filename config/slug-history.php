<?php

declare(strict_types=1);

return [
    // La table qui garde les anciennes adresses.
    'table' => 'past_slugs',

    // Le code de la redirection. 301 : permanent, c'est ce que veulent les
    // moteurs. 308 si vous voulez garder la méthode HTTP.
    'status' => 301,

    // Ajoute le middleware tout seul. Mettez false pour le poser à la main.
    'auto_redirect' => true,

    // Durée de conservation, en jours. null = pour toujours.
    'keep_for_days' => null,

    // La portée par défaut : ce qui rend une adresse unique (une langue, une
    // rubrique). Chaîne vide quand votre site n'en a pas besoin. Vous pouvez
    // aussi mettre une fonction qui reçoit la requête et renvoie la portée.
    'scope' => '',
];
