# 0002: How to time template parts

- Status: Accepted
- Date: 2026-11-02
- Issue: #27

## Decision

The profiler times template parts in two ways, one for classic themes and one for block themes.

**Classic themes:** the profiler uses the `wp_before_load_template` and `wp_after_load_template` actions. WordPress fires them right before and right after it requires a template file in `load_template()`. Both actions pass the full path of the file. They exist since WordPress 6.1, and the plugin needs 6.8.

**Block themes:** a template part is a `core/template-part` block. The profiler uses the `pre_render_block` and `render_block` filters, and only acts on that block. The name of the part is `theme//slug`, such as `twentytwentyfive//header`. The theme defaults to the active theme when the block has no `theme` attribute.

Template parts can nest, so the profiler keeps a stack of start times for each way. For each part, the report has the name, the number of calls and the total time.

## Options we compared

| Option | Start | End | Result |
| --- | --- | --- | --- |
| `get_template_part` and `get_template_part_{$slug}` actions | Yes | No | Rejected. These actions fire before WordPress looks for the file. No action fires at the end. |
| `wp_before_load_template` and `wp_after_load_template` actions | Yes | Yes | Chosen for classic themes. |
| `debug_backtrace()` or an output buffer for each file | Yes | Yes | Rejected. Too slow, and it changes the request it measures. |
| `pre_render_block` and `render_block` filters on `core/template-part` | Yes | Yes | Chosen for block themes. |

The load template actions also cover `get_header()`, `get_footer()`, `get_sidebar()` and any `locate_template()` call that loads the file. Those files are template parts too, so the report includes them.

## Limits

- `comments_template()` and `get_search_form()` require their files directly, without `load_template()`. The profiler does not time them. The same is true for any file a theme loads with `include` or `require`.
- The time of a part includes the parts nested inside it, and every hook that fires while it runs.
- `load_template()` can use `require_once`. A second call for the same file runs nothing, but still counts as a call of about 0 ms.
- If a part stops the request, for example with `wp_die()`, the end action never fires. The profiler drops that part.
- Another plugin can return content from `pre_render_block` instead of rendering the block. The profiler does not time that template part, because it did not run.
- A plugin can render a block by calling `WP_Block::render()` directly. That path fires `render_block` but not `pre_render_block`. If the block is a template part, the profiler ignores it, because it has no start time.
