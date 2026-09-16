<?php

declare(strict_types=1);

return [

    /*
    |---------------------------------------------------------------------------
    | Palette
    |---------------------------------------------------------------------------
    |
    | The colours a chart draws with: the background it sits on and one
    | colour per series, in order. Every series colour must reach 3:1 against
    | the background (WCAG 1.4.11, non-text contrast). A palette that does
    | not is refused: the chart field will not save with it, and the chart
    | will not render with it. A chart field can override this palette in
    | its blueprint settings, and that override is checked when the blueprint
    | saves.
    |
    | Series are also told apart by fill pattern, dash and marker shape, so
    | colour is never the only difference. The defaults below clear 3:1 on
    | white by a margin. If your pages are not white, set the background here
    | to what they are, because that is what the check is against.
    |
    */

    'palette' => [
        'background' => '#ffffff',
        'series' => ['#0b5fa5', '#c2410c', '#15803d', '#7e22ce', '#b91c1c', '#0f766e', '#a16207', '#4b5563'],
    ],

    /*
    |---------------------------------------------------------------------------
    | Chart data table
    |---------------------------------------------------------------------------
    |
    | Every chart renders its data table beneath the picture, inside a
    | <details> element. 'open' shows it expanded; 'collapsed' shows only the
    | summary line until a reader opens it. There is no setting that removes
    | it. A chart field can choose either in its blueprint settings.
    |
    | Collapsed tables are not printed by most browsers, so a site that
    | prints its pages should leave this open.
    |
    */

    'data_table' => 'open',

];
