<?php

// Notificações do app.
return [

    // Por onde cada notificação sai. 'database' = sininho no app.
    // O push (Firebase Cloud Messaging) entra aqui como mais um canal.
    'channels' => ['database'],

    // Quantas aparecem na lista do sininho.
    'page_size' => 30,
];
