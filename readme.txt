=== Nori for Elementor ===
Contributors: medlgeoff
Donate link: https://ko-fi.com/E6H3280AYK
Tags: elementor, atomic, global classes, animations, design system
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 0.24.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Editable Atomic global classes and variables, plus lightweight animation and interaction modules, for Elementor 4.

== Description ==

Nori gives Elementor 4's Atomic editor a ready-made design system and a set of small, well-behaved interaction modules.

**Classes and variables you can edit**

* 92 global classes: container widths, section spacing, padding and gap scales, borders, buttons (a look, an optional size and shape, plus effects), hover effects, a type scale and text helpers. Each one has desktop, tablet and mobile values.
* A type scale: display, H1 to H6, subtitle, lead, body, small print, caption, overline and label, all from one body size and ratio you set. Headings shrink on tablet and mobile; the font family stays with your Elementor site settings.
* 34 starter variables: eleven colors, seven border radii and 16 spacing sizes (size variables need Elementor Pro). Classes use them on desktop, so changing a variable updates every class that uses it; tablet and mobile keep their own values.
* Brand presets: set all your colors (and their dark mode values) in one step from six ready-made palettes or from your own brand color. Every preset keeps text and button labels readable.
* Import a group, a single class, or everything. Classes appear in Elementor's class picker like any class you made yourself.
* Edit them in the Elementor editor. Nori notices which classes you changed, never overwrites your edits when importing again, and lets you reset any class to its default.
* Every class name ends in `-np` (for example `gap-s-np`) so it never clashes with classes a site already has.

**Page templates**

* Four styles to pick from: Studio (quiet and editorial, with large type, lots of white space and hairline rules), Bento (friendly and bold, with rounded tiles in mixed sizes and whole tiles in your accent color), Billboard (poster-sized type and full-width video, with your name across the page and the video playing inside the letters) and Lens (refined and full width, with headlines that rise in word by word and a video that opens to the full screen as you scroll).
* Twenty ready-made pages, five in each style: Home, Services, About, Our work and Contact, shown as a grid of screenshots. One click makes a draft page with sample text.
* Pages are built from thirteen sections (hero, services, about, steps, numbers, reviews, team, gallery, questions, call to action, contact and more), made of ordinary Atomic elements and Nori classes, so you can change anything in Elementor. Colors and type follow your brand preset and type scale, in light and dark mode.
* A site header and footer for the whole site, in any style: in Elementor Pro's Theme Builder when you have Pro, or as Elementor templates Nori shows on every page with the Hello Elementor theme. Edit them once and every page changes.

**Built for MCP**

* Nori is built to be driven over MCP (the Model Context Protocol). On WordPress 6.9 or later it adds twelve abilities that MCP clients, such as AI assistants, can call: check which versions of Elementor, Elementor Pro, Hello Elementor and Nori the site runs (read only), list the templates and modules, add the logo and photos to the media library, set up the site (starter design system, title, logo, header and footer, home page), apply a brand, set the fonts and type scale, make pages from the templates with your own text, change existing pages in place (one section or the whole page), set each page's SEO title, description and sharing image in Yoast SEO, and switch modules on.
* Only administrators can use them, with an application password. Nori never calls an AI service itself.
* The same abilities are available to any code through WordPress's Abilities API.

**Modules**

Switch on only what you need. Every module has a live preview and settings in WP Admin that set the starting point; colors (and, with Elementor Pro, sizes and timings) become Elementor variables you can fine-tune in the editor. Modules stay idle inside the Elementor editor and respect visitors who prefer reduced motion. When a module uses classes, switching it on adds them to Elementor right away. Switching it off keeps the classes on your elements (they just do nothing), and one button deletes them everywhere.

* Smooth Scroll (Lenis, bundled locally): glide duration presets, wheel speed, touch, anchor offset
* Smart Header: hides on scroll down, returns on scroll up or keyboard focus
* Mobile Menu: a menu button on phones (and optionally tablets) that opens the menu below the header (a rounded card under a rounded header, a full-width sheet under a square one), with keyboard and screen reader support
* Marquee: seamless loop with speed, direction and a pause button; copies are hidden from screen readers and search engines
* Scroll animation, pick one for sections: Slide-Fade-Blur (staggered entrance) or Exit-Fade-Up (scroll-linked fade). Split Reveal, for headlines, works alongside either
* Lightbox for images, MP4, YouTube and Vimeo
* Background Video: makes Elementor's own Background Video element ready for real pages: a tint so text stays readable, tidy play and pause buttons, pausing off screen, a still frame for visitors who prefer less motion, an option to skip video on phones (and for data saver), and video playing inside big letters
* Fit Text: poster-sized headlines that fill their container's width on one line at any screen size, stacking their words on phones when a name is long
* Split Reveal: headlines rise into place word by word as they scroll into view; inside a section Slide-Fade-Blur or Exit-Fade-Up is animating, the words wait for the section to arrive
* Scroll Expand: a framed, rounded panel (a video or a big photo) that opens to the full width of the screen as it scrolls up
* Faded Carousel: an endless carousel from any flexbox, with the center slides in full and faded ones peeking in at the sides. Autoplay, drag, swipe and arrow keys. Use it on every screen size, or only on tablet and mobile so desktop keeps your layout
* Video Carousel: an endless carousel of self-hosted, YouTube or Vimeo videos with arrows and a play button; only the center video plays. Arrow and play button styles, sizes and colors are yours to set, and the colors are Elementor variables too
* Button: Ripple Fill, Button: Border Glow, Button: Sliding Underline, Button: Gradient Shift (a sliding five-color gradient, filled or as an outline) and Button: Text Roll (text and icon roll up on hover)
* Dark Mode: a light/dark switch for visitors (theme-toggle-np on any button). Everything that uses Nori's color variables follows, it remembers the choice without cookies, never flashes the light page, and the Elementor editor always stays light. Your own Elementor colors can get a dark version too
* Accessibility enhancements (two-tone focus ring, labels for icon-only links)
* Form Spam Protection: stops spam without a CAPTCHA, on Elementor Pro forms and Atomic forms: an invisible honeypot, a signed one-time pass checked by your server, a too-fast-to-be-human check, optional rate and link limits, a test mode, and a log of what was blocked
* HubSpot Bridge: send Atomic form entries straight to a HubSpot form. Paste the HubSpot embed code; Nori reads the form's fields, gives you a class for your Atomic form and tells you which fields it needs
* Font preconnect for Google Fonts and Adobe Fonts

== Installation ==

1. Install and activate Elementor 4 (the Atomic editor must be on for classes and variables).
2. Upload and activate Nori for Elementor.
3. Go to **Nori** in the WordPress admin menu. On a new site, **Home** offers the whole starter design system in one click: press **Add the starter design system**, then **Save**. Or pick classes and variables one at a time on **Classes** and **Variables**. Under **Motion & effects** and **Forms & site**, switch on the modules you want; their classes are added for you when you save.

== Frequently Asked Questions ==

= What happens to my edits when I import again or update the plugin? =

They are kept. Importing only adds classes that are missing. Use **Reset** on a class to go back to its default.

= What happens if I uninstall Nori? =

Nori's settings are removed. The classes and variables stay in Elementor, because your pages use them.

= Why are some variables marked "Needs Elementor Pro"? =

Elementor only offers size variables (used for the border radius set) with Elementor Pro.

= How does the HubSpot Bridge work? =

Paste a HubSpot form's embed code under **Forms & HubSpot** (open the HubSpot Bridge card). Nori reads the form's fields from HubSpot and creates a class such as `hs-bridge-a1b2c-np`. Add it to an Atomic form. Your form's fields must match the HubSpot form: for each HubSpot field, add an input of the right kind and set its ID (Settings > ID) to the HubSpot field name, for example `email`. Every required HubSpot field needs its input, or HubSpot rejects the entry. On submit the entry goes to HubSpot first, with the visitor's HubSpot tracking cookie; when HubSpot accepts it, Elementor sends its email and shows the success message. No HubSpot API key is needed, and WordPress never stores what visitors type. The Atomic form needs Elementor Pro.

= Does Nori work with MCP and AI assistants? =

Yes, on WordPress 6.9 or later. Nori's MCP server is at `/wp-json/nori/mcp`. It needs an MCP server in WordPress: switch on Elementor's MCP in Elementor's settings (Elementor 4.4 and later), or install the WordPress MCP Adapter plugin. Then create an application password for an administrator (Users > Profile > Application passwords; the site needs HTTPS) and add the server to your AI client with that username and password. The client can then check which versions of Elementor, Elementor Pro, Hello Elementor and Nori the site runs (read only), set up the design system, make and change pages from the templates and set SEO. Pages are made as drafts unless it's asked to publish.

== External services ==

Nori itself sends nothing to Nori or anyone else, with one exception for MCP clients (below). A few optional modules connect to outside services, only when you switch them on and use them:

* **HubSpot (HubSpot Bridge module).** Used to send your Elementor form entries to your HubSpot form.
  * When you set up a bridge in WP Admin, your server and your browser read the form's public definition (field names, labels, required fields and privacy settings) from forms.hsforms.com (or your region's forms-<region>.hsforms.com), and the editor can send one test entry to api.hsforms.com.
  * When a visitor submits a bridged form, their browser sends the entry to api.hsforms.com (or api-<region>.hsforms.com): the fields on the form (for example name and email), the page URL and title, the HubSpot tracking cookie (hubspotutk) if the visitor has one, and any utm_*/gclid values in the page URL. With the module's Respect privacy choices setting on (the default), the cookie is left out when the visitor has opted out: Global Privacy Control, declining HubSpot's cookie banner, or no marketing consent in a consent plugin that uses the WP Consent API.
  * HubSpot terms: https://legal.hubspot.com/terms-of-service · Privacy policy: https://legal.hubspot.com/privacy-policy
* **Images you ask for (MCP abilities).** nori/add-image downloads an image from the https address the administrator's MCP client gives it, into your media library. Nothing about your site is sent beyond a normal web request.
* **Google Fonts and Adobe Fonts (Font Preconnect module).** Adds preconnect hints so visitors' browsers open a connection to fonts.googleapis.com / fonts.gstatic.com or use.typekit.net / p.typekit.net a little earlier. Nori loads no fonts itself; your theme or Elementor decides which fonts load. The browser's normal request data (IP address, user agent) goes to those hosts.
  * Google: https://policies.google.com/terms · https://policies.google.com/privacy
  * Adobe: https://www.adobe.com/legal/terms.html · https://www.adobe.com/privacy/policy.html
* **YouTube and Vimeo (Lightbox and Video Carousel modules).** Only for videos you add to your pages. The player loads from www.youtube-nocookie.com or player.vimeo.com when a visitor presses play. In the Video Carousel, YouTube poster images load with the page from i.ytimg.com.
  * YouTube: https://www.youtube.com/t/terms · https://policies.google.com/privacy
  * Vimeo: https://vimeo.com/terms · https://vimeo.com/privacy

== Source Code ==

Nori for Elementor is open source. The development repository and full source code are publicly hosted and maintained at:
https://github.com/geoffnori/nori-for-elementor

Nori's own JavaScript (assets/admin/app.js and the module scripts) is handwritten, human-readable vanilla JavaScript, shipped exactly as written: no build step, bundler or minification. The one minified file is the bundled Lenis library (assets/vendor/lenis/lenis.min.js); its source is linked under Third-party code.

== Third-party code ==

* Lenis Smooth Scroll (v1.1.14) by darkroom.engineering, MIT License: https://github.com/darkroomengineering/lenis
  Bundled locally for privacy and offline reliability in assets/vendor/lenis.

== Screenshots ==

1. Home: your design system at a glance, with a shortcut into each part.
2. Page templates in four styles (Studio, Bento, Billboard and Lens), made as drafts you edit in Elementor.
3. Colors and dark mode: pick the kind of site and start from a brand preset.
4. The type scale: 14 type classes from one body size and one ratio.
5. Every class Nori adds to Elementor, where it's used, and whether you've edited it.
6. Button effects, each its own module with a live preview.

== Changelog ==

= 0.24.4 =
* Improved: The readme and admin app link Nori's public source repository.

= 0.24.3 =
* Fixed: Sturdier MCP abilities: site setup, the modules list and page anchors handle a missing Elementor piece without an error.
* Changed: The HubSpot Bridge's status in WP Admin shows test entries sent while logged in; visitors' entries still go to HubSpot as before.
* Changed: Setting the site logo sets WordPress's custom logo only.
* Improved: The readme links the source code and Lenis's source.

= 0.24.2 =
* Improved: Clearer readme wording for Nori's MCP abilities.

= 0.24.1 =
* Fixed: On phones a long email address in the site footer no longer runs into the Hours column: the contact details get the footer's full width, in all four styles.

= 0.24.0 =
* Improved: MCP capabilities: nori/apply-brand takes colors: a style guide's exact colors (page background, cards, borders, muted text and the rest), which replace the worked-out values, so builds no longer need the Colors screen for exact colors.

= 0.23.1 =
* Fixed: Clearer description of Nori's abilities in the WordPress Abilities API.

= 0.23.0 =
* Changed: nori/site-status is read only: it reports which versions of WordPress, PHP, Elementor, Elementor Pro, Hello Elementor and Nori the site runs.

= 0.22.0 =
* New: Two heading classes, headline-poster-np (Billboard's bold, all-caps headings) and headline-refined-np (Lens's lighter headings). Billboard and Lens templates use them instead of setting the look on every heading, so changing one class restyles them all. The type classes still set the sizes.
* Improved: Page templates take corner radius, gaps and padding from Nori's classes and variables (card-border-radius-np, border-radius-pill-np, the gap and padding classes), so changing one restyles every page made from them. Sizes that sat between two steps now use the nearest step. Pages you already made keep their look.

= 0.21.2 =
* Fixed: Cleaner input handling in Form Spam Protection and the HubSpot Bridge settings, so WordPress's Plugin Check no longer warns about them (no change in behavior).
* Changed: Translations load the way WordPress does it since 4.6, without an extra load_plugin_textdomain() call.

= 0.21.1 =
* Improved: Split Reveal is listed under Scroll animation next to Slide-Fade-Blur and Exit-Fade-Up, with its own switch, and works with either: a headline inside a section they animate waits for the section to arrive, then its words rise.
* Improved: Each module says how it behaves next to the others: which ones are either-or, which wait their turn, which step aside in one spot, which team up, and which were checked together with no clash. Shown on each module in Nori, listed by nori/list-modules for AI assistants, and on the website.
* Improved: Background videos pause while the Lightbox is open, and Split Reveal leaves video text headings whole.
* Fixed: Fit Text on a heading in a column that hugs its content made the page scroll sideways instead of shrinking the text.

= 0.21.0 =
* New: Two new template styles, Billboard and Lens, each with five pages (Home, Services, About, Our work and Contact) and its own header and footer. Billboard puts your name across the page with video inside the letters, huge service names and bold color blocks. Lens has headlines that rise in word by word, a video that opens to the full screen as you scroll, and rounded panels.
* New: Background Video module for Elementor's own Background Video element: a tint in your band color (even, darker at the bottom or on the left), tidy play and pause buttons, videos pause when they're off screen, hold still for visitors who prefer less motion, and can be skipped on phones. With video-text-np the video plays inside a heading's letters.
* New: Fit Text, Split Reveal and Scroll Expand modules: poster-sized headlines that fill the width, headlines that rise in word by word, and a framed panel that opens to the full width as it scrolls.
* New: Billboard and Lens pages start with a short abstract video loop that ships with Nori. Pick your own video in Elementor, or give its address to the AI over MCP.
* Improved: Making a page from a template switches on the modules its sections need.
* Improved: Each style suggests a heading and body font pair, which nori/list-templates shares with AI assistants for sites without their own fonts.

= 0.20.1 =
* Improved: On phones the header's button moves into the phone menu (it's at the bottom of the open menu), so the header bar has room for long business names.
* Improved: The readme describes Nori's MCP server and its tools, and the template screenshots on the Templates screen are up to date.

= 0.20.0 =
* New: MCP abilities nori/update-page and nori/get-pages: change a page in place (one section's content or style, remove or add sections, or the whole page) and it keeps its address, SEO and any edits made in Elementor to the other sections.
* New: Pages made from templates switch on Smooth Scroll (Snappy, 0.5s) and Exit-Fade-Up, and their sections fade as visitors scroll. If Slide-Fade-Blur is already on, pages use it instead. A site's own scroll speed is kept.
* Improved: Mobile Menu matches its header: under a rounded header bar (Bento) it opens as a rounded card just below it; under a square header (Studio) as a full-width sheet. A tap outside closes it.
* Improved: Galleries lay out any number of photos with every row full, in both styles.
* Improved: Pages made over MCP leave out the reviews section when no real reviews are given.

Older versions: https://noriforelement.com/changelog.html
