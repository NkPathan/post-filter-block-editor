=== Post Filter for Block Editor ===

Contributors:      nawazpathan
Donate link:       https://nkpathan.github.io/
Tags:              gutenberg, block, posts, filter, custom fields
Requires at least: 6.1
Tested up to:      6.9
Stable tag:        1.0.0
Requires PHP:      7.4
License:           GPL-2.0-or-later
License URI:       https://www.gnu.org/licenses/gpl-2.0.html

A Gutenberg block that displays a filterable list of posts by Difficulty Level — with live REST API filtering and server-side rendering.

== Description ==

**Post Filter for Block Editor** adds a native Gutenberg block that renders a grid of posts, each showing its title, excerpt, and a colour-coded Difficulty Level badge (Easy / Medium / Hard).

A dropdown filter on the frontend lets visitors narrow the list by difficulty level without reloading the page — powered by a custom REST API endpoint.

= Features =

* Registers a `difficulty_level` custom post meta field (exposed to the REST API).
* Server-side rendering — the block outputs semantic HTML on the frontend and shows a live preview in the editor via `ServerSideRender`.
* REST endpoint at `/wp-json/pfbe/v1/posts?difficulty=<easy|medium|hard>` for AJAX filtering.
* Works on pages with multiple block instances simultaneously.
* Inspector Controls sidebar lets editors set the default difficulty filter per block.
* No build step, no npm, no webpack — drop the folder in and go.
* Fully sanitized and escaped output; nonce-protected REST requests.
* BEM-style CSS classes scoped to `.pfbe-*` to avoid theme conflicts.

= Custom Field =

The block uses a registered post meta field `difficulty_level` with three allowed values:

* `easy`
* `medium`
* `hard`

Assign this meta to your posts via the Classic editor's Custom Fields meta box, WP-CLI, or FakerPress (see the Installation section for quick setup instructions).

= Block Attributes =

| Attribute            | Type   | Default | Description                              |
|----------------------|--------|---------|------------------------------------------|
| `selectedDifficulty` | string | `""`    | Default filter pre-selected for visitors |

= REST API =

`GET /wp-json/pfbe/v1/posts`
`GET /wp-json/pfbe/v1/posts?difficulty=easy`
`GET /wp-json/pfbe/v1/posts?difficulty=medium`
`GET /wp-json/pfbe/v1/posts?difficulty=hard`

Returns JSON: `{ "html": "<article>…</article>" }`

== Installation ==

= WordPress Admin =

1. Download the plugin zip file.
2. In your WordPress admin go to **Plugins → Add New → Upload Plugin**.
3. Choose the zip file and click **Install Now**.
4. Click **Activate Plugin**.

= Manual (FTP) =

1. Unzip `post-filter-block-editor.zip`.
2. Upload the `post-filter-block-editor` folder to `/wp-content/plugins/`.
3. Go to **Plugins → Installed Plugins** and activate **Post Filter for Block Editor**.


**Via WP-CLI (fastest):**

`wp post meta set <post_id> difficulty_level easy`
`wp post meta set <post_id> difficulty_level medium`
`wp post meta set <post_id> difficulty_level hard`

**Via the editor:**

1. Edit a post.
2. Open **Screen Options** (top right) and enable **Custom Fields**.
3. In the Custom Fields meta box, add Name: `difficulty_level`, Value: `easy`, `medium`, or `hard`.
4. Update the post.

= Adding the block =

1. Open any page or post in the block editor.
2. Click the **+** inserter and search for **Post Filter Difficulty Level**.
3. Insert the block.
4. In the right sidebar under **Filter Settings**, choose a default difficulty.
5. Publish the page and test the dropdown on the frontend.

== Frequently Asked Questions ==

= Does this block require a build step or Node.js? =

No. The plugin uses `wp.*` browser globals and vanilla JavaScript. There is no webpack, npm, or compilation required. Simply upload and activate.

= Can I place multiple instances of this block on one page? =

Yes. Each block instance generates a unique DOM ID so multiple blocks on the same page work independently without conflicts.

= How do I change the number of posts shown? =

Open `post-filter-block-editor.php` and find the `posts_per_page` argument inside `pfbe_query_posts()`. Change `-1` to your preferred number.

= Will this slow down pages that don't use the block? =

No. Frontend scripts and styles are only enqueued on singular pages that actually contain the block (`has_block()` check), so there is zero performance impact on other pages.

= Is the REST endpoint publicly accessible? =

The `/wp-json/pfbe/v1/posts` endpoint returns only published posts and is read-only. It is intentionally public (`__return_true`) because unauthenticated visitors need to use the filter. It accepts only the `difficulty` parameter, which is validated against an allowed list.

= Can I style the cards to match my theme? =

Yes. All CSS classes are prefixed with `pfbe-` and the stylesheet (`style.css`) contains straightforward CSS that you can override in your theme's `style.css` or a child theme.



== Changelog ==

= 1.0.0 =
* Initial release.
* Registers `difficulty_level` custom post meta.
* Block with server-side rendering and `ServerSideRender` editor preview.
* Custom REST API route `/wp-json/pfbe/v1/posts`.
* Live AJAX dropdown filter on the frontend.
* BEM-scoped CSS with Easy / Medium / Hard badge colours.
* Supports multiple block instances per page.

== Upgrade Notice ==

= 1.0.0 =
Initial release — no upgrade steps required.
