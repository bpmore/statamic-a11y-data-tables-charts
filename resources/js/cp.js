import { components } from '@statamic/cms/api';

import A11yTableFieldtype from './fieldtypes/A11yTableFieldtype.vue';
import A11yChartFieldtype from './fieldtypes/A11yChartFieldtype.vue';

/**
 * The addon's control panel assets: the two editors, registered under the
 * names Statamic looks for, `<handle>-fieldtype`.
 */
components.register('a11y_table-fieldtype', A11yTableFieldtype);
components.register('a11y_chart-fieldtype', A11yChartFieldtype);
