<?php

/**
 * Renders a standardized grid of statistics cards for the Admin module.
 *
 * @param array $cards Array of card definitions. Each card should have:
 *                     - 'label' (string): Title of the card.
 *                     - 'value' (mixed): Main numeric or string value.
 *                     - 'subtext' (string): Supporting text below value.
 *                     - 'isCritical' (bool): If true, value and subtext will use red styling.
 *                     - 'animate' (bool): If true, subtext will have animate-pulse.
 *                     - 'indicator' (string): Optional HTML for indicators like status dots.
 * @param int   $cols  Optional. Number of columns in the grid. Default 3.
 */
function render_admin_stats_cards(array $cards, int $cols = 3)
{
    echo '<div class="grid grid-cols-' . $cols . ' justify-center gap-5 w-full">';

    foreach ($cards as $card) {
        $label      = $card['label'] ?? 'Unknown';
        $value      = $card['value'] ?? '0';
        $subtext    = $card['subtext'] ?? '';
        $isCritical = $card['isCritical'] ?? false;
        $animate    = $card['animate'] ?? false;
        $indicator  = $card['indicator'] ?? '';

        // Standard logic for values: format if numeric, else use as-is
        $displayValue = is_numeric($value) ? number_format((float)$value) : $value;

        // Styling based on criticality 
        $valueClass   = $isCritical ? 'text-red-600' : 'text-slate-900';
        $subtextClass = $isCritical
            ? 'text-red-600 font-bold uppercase tracking-widest'
            : 'text-slate-500 font-medium';
        $animateClass = $animate ? 'animate-pulse' : '';

        echo '
        <div class="flex flex-col justify-between bg-white border border-gray-200 rounded-lg h-[180px] p-8 text-left transition-all duration-300 hover:shadow-lg group relative [container-type:inline-size]">
            <div class="text-[11px] font-black text-slate-500 uppercase tracking-[0.15em] leading-none mb-4">
                ' . htmlspecialchars((string)$label) . '
            </div>
            
            <div class="flex items-center gap-3 w-full overflow-hidden my-auto">
                <div class="text-[clamp(1.5rem,18cqw,2.25rem)] font-black ' . $valueClass . ' tracking-tight truncate leading-none">
                    ' . $displayValue . '
                </div>
                ' . $indicator . '
            </div>

            <div class="mt-6">
                <p class="text-[12px] ' . $subtextClass . ' ' . $animateClass . ' italic leading-relaxed truncate opacity-90">
                    ' . htmlspecialchars((string)$subtext) . '
                </p>
            </div>

            <div class="absolute top-0 left-0 w-0 h-1 bg-red-600 group-hover:w-full transition-all duration-500"></div>
        </div>';
    }

    echo '</div>';
}
