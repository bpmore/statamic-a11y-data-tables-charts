import { components } from '@statamic/cms/api';

import FurrowTableFieldtype from './fieldtypes/FurrowTableFieldtype.vue';
import FurrowChartFieldtype from './fieldtypes/FurrowChartFieldtype.vue';

/**
 * The addon's control panel assets: the two editors, registered under the
 * names Statamic looks for, `<handle>-fieldtype`.
 */
components.register('furrow_table-fieldtype', FurrowTableFieldtype);
components.register('furrow_chart-fieldtype', FurrowChartFieldtype);
