<?php

use Illuminate\Support\Facades\Schedule;

// Sauvegarde quotidienne de la base et des fichiers, conservée 30 jours
// (cahier des charges, section 11). Nécessite `schedule:work` ou le cron Laravel.
Schedule::command('backup:clean')->dailyAt('01:00');
Schedule::command('backup:run')->dailyAt('01:30');
Schedule::command('backup:monitor')->dailyAt('03:00');

// Métriques du tableau de bord Horizon.
Schedule::command('horizon:snapshot')->everyFiveMinutes();

// Alertes quotidiennes de planning et de budget (RG7.6, RG7.7, RG10.3).
Schedule::command('erp:send-alerts')->dailyAt('07:00');
