<?php
/** Accessible Comparison Table component renderer. */
declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

/** Return stored rich text from one validated core block. */
$content_of = static function (array $block): string {
    $content = $block['attrs']['content'] ?? '';
    if (is_scalar($content) && trim((string) $content) !== '') {
        return trim((string) $content);
    }

    $inner_html = $block['innerHTML'] ?? '';
    if (! is_scalar($inner_html)) {
        return '';
    }

    $inner_html = trim((string) $inner_html);
    if (preg_match('/^<[^>]+>(.*)<\/[^>]+>$/s', $inner_html, $matches) !== 1) {
        return '';
    }

    return trim($matches[1]);
};

$inner = ($block instanceof WP_Block && isset($block->parsed_block['innerBlocks']))
    ? (array) $block->parsed_block['innerBlocks']
    : [];

if ($inner === [] || ! intercargo_section_composition_is_valid('comparison-table', $inner)) {
    return;
}

$structure = $inner[0]['innerBlocks'] ?? [];
$header_blocks = $structure[0]['innerBlocks'] ?? [];
$row_blocks = $structure[1]['innerBlocks'] ?? [];

if (count($header_blocks) !== 3 || $row_blocks === []) {
    return;
}

$headers = array_map($content_of, $header_blocks);
if (in_array('', $headers, true)) {
    return;
}

$labels = array_map(
    static fn(string $header): string => wp_strip_all_tags($header),
    $headers
);
?>
<div class="comparison-table">
  <table class="comparison-table__table">
    <caption class="screen-reader-text"><?php esc_html_e('Comparison table', 'intercargo-vite'); ?></caption>
    <colgroup>
      <col class="comparison-table__service-column">
      <col class="comparison-table__cost-column">
      <col class="comparison-table__speed-column">
      <col class="comparison-table__best-column">
    </colgroup>
    <thead>
      <tr>
        <th scope="col" class="comparison-table__blank-heading"><span class="screen-reader-text"><?php esc_html_e('Option', 'intercargo-vite'); ?></span></th>
        <?php foreach ($headers as $header) : ?>
          <th scope="col"><?php echo wp_kses_post($header); ?></th>
        <?php endforeach; ?>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($row_blocks as $row) :
          $cells = is_array($row['innerBlocks'] ?? null) ? $row['innerBlocks'] : [];
          if (count($cells) !== 4) {
              continue;
          }
          $values = array_map($content_of, $cells);
          $classes = preg_split('/\s+/', trim((string) ($row['attrs']['className'] ?? ''))) ?: [];
          $highlighted = in_array('is-style-highlighted', $classes, true);
      ?>
        <tr<?php echo $highlighted ? ' class="is-highlighted"' : ''; ?>>
          <th scope="row"><?php echo wp_kses_post($values[0]); ?></th>
          <?php for ($index = 1; $index < 4; $index++) : ?>
            <td data-label="<?php echo esc_attr($labels[$index - 1]); ?>"><span><?php echo wp_kses_post($values[$index]); ?></span></td>
          <?php endfor; ?>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
