# 0006 — Chart.js pour les graphiques

- **Statut** : accepté
- **Date** : 2026-10-29

## Décision

**Chart.js 4** avec `vue-chartjs`, enregistré module par module (seuls les éléments utilisés sont embarqués).

## Pourquoi pas ApexCharts

- **Poids** : l'usage terrain en 3G impose des pages légères. Chart.js se charge en ne gardant que les éléments utilisés (barres, lignes, échelles) ; ApexCharts embarque un moteur SVG complet.
- Les graphiques prévus (consommation du budget, avancement, trésorerie) sont des barres, lignes et anneaux : couverts par Chart.js, en rendu `canvas`, performant sur un smartphone d'entrée de gamme.
- Wrapper Vue maintenu et typé.

Le composant `components/charts/BarChart.vue` sert de modèle pour les suivants.
