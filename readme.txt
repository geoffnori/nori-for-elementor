=== Nori for Elementor ===
Contributors: medlgeoff
Donate link: https://ko-fi.com/E6H3280AYK
Tags: elementor, atomic, global classes, animations, design system
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 0.24.3
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
https://github.com/geoffnori/nori-wordpress-plugin

Nori's own JavaScript (assets/admin/app.js and the module scripts) is handwritten, human-readable vanilla JavaScript, shipped exactly as written: no build step, bundler or minification. The one minified file is the bundled Lenis library (assets/vendor/lenis/lenis.min.js); its source is linked under Third-party code.

== Third-party code ==

* Lenis Smooth Scroll (v1.1.14) by darkroom.engineering, MIT License: https://github.com/darkroomengineering/lenis
  Bundled locally for privacy and offline reliability in assets/vendor/lenis.

== Changelog ==

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

= 0.19.0 =
* New: Mobile Menu module: on phones (and optionally tablets) the header's menu becomes a menu button that opens a full-width menu below the header. A real button for keyboards and screen readers, Esc to close, focus kept inside, and the page locked behind it. Headers made by Nori include it and switch it on.
* New: MCP ability nori/set-seo: each page's SEO title, meta description, sharing image and focus keyphrase, plus the organization and default sharing image, written into Yoast SEO.
* Improved: A header made by Nori shows the site's logo even when the logo isn't given again, and the MCP server's instructions ask for a real image in every image slot and SEO on every page.
* Improved: The type scale previews read "Nori means seaweed in Japanese".

= 0.18.1 =
* Fixed: Logos and photos given over MCP by media library id work again with Elementor 4.3.4 and later ("The image of e-image could not be set").
* Fixed: A header and footer made over MCP no longer show the templates' sample phone number, address and hours when the business's own aren't given.

= 0.18.0 =
* New: New MCP abilities: see which versions of Elementor, Elementor Pro, Hello Elementor and Nori the site runs (nori/site-status), add your logo and photos to the media library (nori/add-image), and set your fonts and type scale (nori/set-typography).
* Improved: The MCP server's instructions describe the order to use the tools in.

= 0.17.2 =
* Fixed: The HubSpot Bridge no longer sends the visitor's HubSpot tracking cookie when they have opted out of tracking: Global Privacy Control, declining HubSpot's cookie banner, or no marketing consent in a consent plugin that uses the WP Consent API. The entry still reaches HubSpot.
* New: Respect privacy choices (GDPR, Global Privacy Control), a setting on the HubSpot Bridge, on by default. Switch it off only if you handle consent another way.

= 0.17.1 =
* Fixed: No more "translation loaded too early" notice in the debug log when Elementor is switched off, and Home says "Not added yet" for colors on a fresh site instead of "Custom".

= 0.17.0 =
* New: Full reset, under Tools. It puts Nori back the way a fresh install starts: Nori's classes and variables are deleted from Elementor, modules switch off, colors, type scale and settings go back to the defaults, HubSpot bridges and the spam log are cleared, and Nori's header and footer move to the trash. You export a backup first, and pages you made from templates and your own classes stay.
* Improved: Export and import are now called Export Nori Theme JSON and Import Nori Theme JSON, and the files are named nori-theme-your-site-date.json. Importing a backup brings everything back after a reset.

= 0.16.1 =
* Fixed: The Lightbox only opens real web addresses for videos and images, so a link on a page can no longer run a script when someone clicks it.
* Fixed: Reading a HubSpot form's labels in WP Admin can no longer run code from the form's text.
* Improved: Form Spam Protection finds the visitor's real address behind a local proxy or load balancer, so one busy visitor can't lock everyone else out. Sites behind a CDN can set it with the nori_fe_visitor_ip filter.
* Improved: The spam log and the HubSpot Bridge status can't be flooded by one visitor any more.
* Improved: Imported variable values are checked before they reach Elementor.
* Improved: The readme lists every outside service the modules can connect to (HubSpot, Google Fonts, Adobe Fonts, YouTube and Vimeo).
* Improved: The Lightbox plays YouTube videos from youtube-nocookie.com.
* Fixed: No more PHP warnings for checkbox fields in classic forms, and a faster Home screen.

= 0.16.0 =
* New: Two template styles, Studio and Bento, replace the old page looks. Each has five pages (Home, Services, About, Our work and Contact), and Page templates has a style picker to see both.
* New: The site header and footer come in both styles. Pick one when you make them, or switch later; the old ones go to the trash, so you can get them back.
* New: A Steps section for how your service works, and new hero, services and about layouts.
* New: Two color variables, band and on-band, for dark strips like the call to action and the footer. They follow your colors, so those strips stay readable on dark sites and in dark mode. Dark Mode has a dark version of each.
* New: MCP abilities. On WordPress 6.9 or later, an MCP client can set up the site, apply a brand, make pages from the templates with your own text and switch modules on. Administrators only.
* Changed: The six old page templates are gone. Pages you already made from them stay as they are.

= 0.15.0 =
* New: A new Nori admin. One screen with a sidebar (Home, Page templates, Header & footer, Colors & dark mode, Type scale, Variables, Classes, each group of modules, Import & export) and one save bar for everything. A change only counts while it differs from what's saved, and the sidebar marks the sections you've changed.
* New: Colors & dark mode in one place: brand presets, Generate colors from your own brand color, the nine colors with their dark versions side by side, and your own Elementor colors' second versions with Suggest.
* New: Four site types: a light site, a light site with a dark switch, a dark site, and a dark site with a light switch (your design stays dark; visitors can switch to a light version).
* New: On a fresh site, Home adds the whole starter design system in one click.
* New: Search across classes, variables, modules and settings (press /).
* Improved: Module cards open in place, with a live preview, their settings, and the classes each one adds. Switching a module on only adds classes to your library; nothing on your pages changes until you use them.
* Improved: The HubSpot Bridge has a step-by-step guide, and each page template says which classes and variables it adds.
* Improved: Deleting classes or variables asks first, in the page, and says how many pages use them.
* Fixed: Resetting a color setting that follows a Nori color (like the ripple color) puts it back on that color instead of a fixed value.

= 0.14.1 =
* Improved: The site header and footer are now called Main Header NP and Main Footer NP in Elementor. Ones made with 0.14.0 are renamed when you update, unless you gave them your own name.

= 0.14.0 =
* New: Templates tab. Six page templates (Home: local service, Home: simple, Services, About, Our work and Contact) in a grid of screenshots. Create draft page makes the page with sample text, your site title and your email, ready to edit in Elementor.
* New: Site header & footer. One header and one footer for the whole site. With Elementor Pro they're made in Theme Builder and set to show on the entire site; without Pro they're saved as Elementor templates and shown on every page of a site using the Hello Elementor theme. If you already have a Theme Builder header or footer, Nori's are saved without display conditions, so yours stay.
* New: Twelve sections the pages are made from, including a questions section built on Elementor's accordion that tells search engines about your answers (FAQ structured data).
* New: A small set of line icons for the templates. They're ordinary Elementor SVG elements, so you can swap them for your own.
* Improved: Sample reviews and team members are clearly placeholders, so nothing made-up gets published by accident. Change the sample numbers to your own before you publish.

= 0.13.0 =
* New: Two color variables, line-np for borders and dividers, and muted-np for quieter text such as intros and captions. Both are made from your text and background colors, so they match your brand, and both have dark mode versions. Muted text always stays readable on your background and surface colors.
* New: Brand presets set all nine colors, and the preview shows the line and muted colors too.
* Fixed: border-top-np and border-bottom-np used a faint white line that couldn't be seen on light backgrounds. They now use line-np. If you edited either class, your edit is kept.
* Improved: Sites already using Nori's colors get line-np and muted-np on update, worked out from the colors you already have. Nothing you set is changed.

= 0.12.0 =
* New: Form Spam Protection checks every entry on your server with a signed pass. After the page loads, the visitor's browser quietly gets a one-time pass from your site and sends it with the entry; entries without a valid pass are turned away. This stops bots that post straight to WordPress without opening the page, which the hidden field alone couldn't. It works with page caching, on classic Elementor Pro forms and on Atomic forms, which until now were only checked in the browser.
* New: The too-fast-to-be-human check now also runs on your server, so bots can't skip it.
* New: A limit on how many entries one visitor can send in 10 minutes (5 by default). Logged-in editors are never limited, and IP addresses are never stored.
* New: A limit on links in one entry (3 by default), since spam is usually full of them.
* New: Test mode. Nothing is blocked; anything that would have been is only logged, so you can check nothing real gets caught before switching blocking on.
* New: See what was blocked. The Form Spam Protection card shows how many entries were blocked in the last 7 days and why, with the latest ones, and the Overview tells you the weekly total. Only the time, the reason and the page are kept, never what was typed or who sent it.

= 0.11.1 =
* New: A Support Nori button in the Nori header, a line in the footer and a link on the Plugins screen, if you'd like to chip in on Ko-fi. Nori stays free either way. It's a plain link: nothing from Ko-fi loads in your WP Admin or on your site.

= 0.11.0 =
* New: Your own colors in dark mode. The Dark Mode card lists every color variable in Elementor, including ones you add later, and each stays the same in dark mode until you give it a dark version: a custom color, one of your other colors (brand-red uses brand-red-light), or one of Nori's colors. Suggest offers a dark version with the same hue, and Suggest for the rest does it for every color you haven't set.
* Improved: A dark version follows its variable when you rename it in Elementor, and never makes two colors follow each other in a loop.
* Improved: Exports carry the dark versions of your colors, by name, so they carry over to a site with the same colors.

= 0.10.1 =
* New: Every module color setting picks a color variable: one of Nori's colors, any of your own Elementor color variables, or a custom color. Ripple, glow, the Video Carousel arrows and the focus ring use your palette by default (accent-np, on-accent-np, primary-np, background-np, text-np), so they follow brand presets and Dark Mode. A custom color still becomes its own Elementor variable, like ripple-color-np. If you had fine-tuned one of those in Elementor, your color is kept.
* Fixed: Button: Ripple Fill on a flexbox button. The circle stays inside the button (Elementor's own container style was switching the clipping off), the text no longer disappears under it in the Elementor editor, and the circle fills wide buttons all the way.
* Fixed: Button: Gradient Shift keeps its text color when a look class like btn-primary-np is on the same button, at rest, on hover and when pressed. The text color is now set on the class itself, so you can still change it in Elementor.
* Fixed: Smart Header on a flexbox keeps its full width and stays above the page content.
* Changed: btn-arrow-np is now called svg-mover-np. Sites that use it are updated in place: it's the same class with a new name, so your pages keep it and nothing else changes.

= 0.10.0 =
* New: Type scale. Fourteen type classes (type-display-np, type-h1-np to type-h6-np, type-subtitle-np, type-lead-np, type-body-np, type-small-np, type-caption-np, type-overline-np, type-label-np) built from one body size and ratio. Change the scale under Classes > Type scale and every type class you haven't edited in Elementor follows. Headings shrink on tablet and mobile; body text and smaller keep one size; the font family stays with your Elementor site settings.
* New: Brand presets under Variables > Starter colors. Pick Nori lime, Ocean, Ember, Forest, Grape or Graphite, or start from your own brand color, preview it light and dark, and apply it to Elementor in one step.
* New: Dark Mode module. Put theme-toggle-np on a button and visitors can switch the site to dark. It works by giving Nori's color variables their dark values, so it never rewrites Elementor's styles, and the editor always shows the light version. Use hide-in-dark-np and hide-in-light-np to swap logos or icons.
* New: btn-sharp-np for square button corners, next to btn-pill-np; soft corners stay the default.
* New: on-accent-np, a seventh starter color for text on the accent. The primary button uses it, so its label stays readable in dark mode.
* Improved: Exports carry the type scale and brand choice, and an import shows a type scale change in its preview.
* Improved: Button animations are easier to find. The Modules category is now called Button effects, and Classes > Buttons lists every effect with its on/off state and a link to its settings.

= 0.9.2 =
* Improved: Nori has a new logo, a seaweed mark, in the WP Admin header and the admin menu. It's a single-color SVG, so it stays crisp at any size and takes the color that reads best where it sits.

= 0.9.1 =
* Changed: btn-primary-np is now the accent button (accent fill, dark text, dark on hover) and btn-secondary-np the dark one, so your main call to action carries the brand color. Classes you edited in Elementor keep your edits.
* New: Every button look has a pressed state: a 1px nudge while the button is held down. It's Elementor's Active state on each class, so you can restyle it in the editor, next to Hover (which also covers keyboard focus).
* Improved: Button: Gradient Shift runs its colors there and back, so the slide is smoother and never shows a muddy seam. New default colors follow Nori's lime brand; if you kept the old defaults, they move to the new ones.
* Fixed: The filled gradient button no longer shows a thin stroke around its edge when a look class gives it a border.
* Fixed: With Button: Text Roll, the incoming copy of an icon no longer peeks out below the text before you hover.

= 0.9.0 =
* New: Button: Gradient Shift module. btn-gradient-np gives a button a five-color gradient that slides across it on hover and keyboard focus; btn-gradient-outline-np does the same as an outline that follows the button's corner radius and keeps the inside see-through. Set the five colors, the angle, the speed and the outline width; the colors are Elementor variables too.
* New: Button: Text Roll module. With btn-roll-np, a button's text and icon roll up on hover and keyboard focus while copies roll in from below (or down, if you prefer). It works with any look, any icon and the other button effects, and screen readers still read the button's name once.

= 0.8.1 =
* New: The Video Carousel works with YouTube and Vimeo: Elementor's YouTube widget, a YouTube or Vimeo embed, or a link in each slide. Until someone presses play, the slide shows the video's thumbnail, so the player loads only when needed (YouTube from youtube-nocookie.com) and no cookies are set before that. Add a data-nori-poster attribute to use your own thumbnail.
* New: Video Carousel styling. Pick the arrow style (circle, rounded square or minimal), the play button style (outline ring, filled circle or icon only), their sizes, a dark tint behind the play button, and six colors: arrow background and icon, both on hover too, and the play button color and fill.
* Improved: The Video Carousel colors are also Elementor variables (vc-arrow-bg-np, vc-play-color-np and more), so you can change them in the Elementor editor.
* Improved: Module settings tied to Elementor variables keep following those variables even if you rename them in Elementor.

= 0.8.0 =
* New: Faded Carousel module. Put faded-carousel-np on a flexbox and its children become an endless carousel: the center slides in full (1, 3 or 5 on desktop) and faded slides peeking in at both sides. It autoplays, pauses on hover, keyboard focus and touch, and moves by drag, swipe or arrow keys.
* New: faded-carousel-mobile-np, the same carousel on tablet and mobile only. On desktop the flexbox keeps the layout you built.
* New: Video Carousel module. Put video-carousel-np on a flexbox of videos for an endless carousel with arrows and a large play button. Only the center video plays, it pauses when it moves away, and a drag never starts a video by accident.
* Improved: Carousel copies made for the loop are hidden from screen readers and search engines and can't be reached with the Tab key, so each slide is read and indexed once.

= 0.7.5 =
* Improved: If your HubSpot form uses consent checkboxes, the HubSpot Bridge now lists each one in the field table with the ID its Atomic checkbox needs (like consent-to-process), and the page check tells you when one is missing.
* Improved: For forms that use legitimate interest, the privacy note is now one short line: the privacy info is sent for you, and the notice to show near your Send button is one click away.

= 0.7.4 =
* Improved: The HubSpot Bridge makes it clear that your Atomic form's fields must match the HubSpot form: each input's ID (Settings > ID) must be the HubSpot field name. Every field now shows which Atomic field to use and its option values, and a required file upload (which HubSpot can't take through the bridge) is flagged.

= 0.7.3 =
* Fixed: The HubSpot Bridge reads forms made in HubSpot's newer form editor in full, including optional and hidden fields, from the same place HubSpot's own embed reads them. Works for every HubSpot region.
* New: The bridge sends your HubSpot form's privacy settings (legitimate interest or consent checkboxes) with every entry, like HubSpot's own form, and tells you which notice or checkboxes to add to your form.
* New: Hidden HubSpot fields are filled in automatically: preset values as set in HubSpot, and tracking fields like utm_source and gclid from the page address.

= 0.7.2 =
* Fixed: The HubSpot Bridge works with HubSpot's newer forms, which don't publish their fields. Nori asks HubSpot which fields are required (HubSpot's answer to an empty test entry, so nothing is saved) and suggests common optional fields to add with one click.
* Improved: If HubSpot says a field isn't on its form, the bridge leaves that field out and sends the entry anyway, so no lead is lost. The bridge card tells you which field to remove.

= 0.7.1 =
* Fixed: The HubSpot Bridge now reads a form's fields even when HubSpot turns away requests from your web server, or your host's security filter blocks pasted embed code. Your browser reads the form, like HubSpot's own embed does, and only the field list is sent to WordPress.
* Improved: When the fields can't be read, the message says what happened and what to try.

= 0.7.0 =
* New: HubSpot Bridge. Send Elementor Atomic form entries straight to a HubSpot form, and design the form any way you like in Elementor. Paste a HubSpot embed code and Nori reads the form's fields, creates a class to add to your form and lists the fields the form needs. Create as many bridges as you like, one class each.
* New: The bridge checks your pages. Every page that uses a bridge shows whether its form has all the fields HubSpot requires, and the last result from HubSpot.
* New: Bridges come along when you export your Nori setup and import it on another site.
* Improved: Form Spam Protection now protects Atomic forms too, and explains how it stops spam without a CAPTCHA.
* Improved: A new Forms category on the Modules tab for Form Spam Protection and the HubSpot Bridge.

= 0.6.1 =
* Improved: Border Glow now glows in Nori's accent color (#D6FF00) by default, matching the rest of the starter palette. If you never changed the glow color, it updates automatically; a color you picked is kept.

= 0.6.0 =
* New: Simpler buttons. Add one look (btn-primary-np, btn-secondary-np, btn-outline-np or btn-text-np), optionally a size (btn-sm-np or btn-lg-np) and btn-pill-np for rounded ends, then any effect class.
* New: fx-hover-lift-np, a gentle lift on hover for any element, not just buttons.
* New: Spacing variables for gaps, padding, section spacing and the side gutter. Classes are connected to them on desktop, so one change updates everything that uses them.
* Improved: Adding a class also adds the variables it uses, and the screen tells you which ones before you save.
* Improved: Deleting a variable no longer breaks classes. They keep its value and show as edited, and Reset reconnects them.
* Improved: A leaner, more focused library. Classes that were too generic (flex helpers, extra container widths, some spacing and button variants) have been removed and are cleaned up automatically when you update.
* Improved: Modifier and effect classes now reliably win over the look they are combined with.
* Fixed: Checkboxes no longer show a second checkmark on some screens.
* Fixed: The Save changes button no longer has a white glow.

= 0.5.0 =
* New: Export and import. Download your whole Nori setup as a file (modules and their settings, variables, and classes including your edits) and import it on another site. A preview shows exactly what will change before anything is applied, and you decide what happens to classes edited on both sites.
* New: The Nori for Elementor website, noriforelement.com, is linked from the plugin.
* Improved: Clearer, more readable changelog.

= 0.4.2 =
* Improved: Larger headings and more prominent main buttons, so the next step is always easy to spot.

= 0.4.1 =
* Improved: A clean new menu icon that matches your WordPress admin color scheme.

= 0.4.0 =
* New: A redesigned settings screen that fits right into WordPress, with Overview, Classes, Variables, Modules and Changelog tabs.
* New: Review before you save. Pick what you want in any category, see every pending change at a glance, then save or discard. Every category has Enable all, Disable all, Reset all and Delete all.
* New: Overview page showing what you use, which pages use Nori classes, site health checks with suggested fixes, and exactly what Nori adds to your pages.
* Improved: Modules are organized into clear categories: Scrolling, Scroll animation, Buttons, Motion & media and Site-wide.
* Improved: Variables can now be removed as well as added.

= 0.3.0 =
* New: Fine-tune module settings right in the Elementor editor. Module colors become Elementor variables, and with Elementor Pro so do sizes and timings. Changes made in Elementor also show up in Nori.
* Improved: Border Glow and the ripple's text color can be adjusted per class or per button in Elementor.
* Improved: Plugin updates refresh the default classes you haven't touched, and never overwrite your edits.

= 0.2.0 =
* New: Switching on a module adds its classes to Elementor automatically. Switching it off keeps your classes in place, and a single button removes them everywhere if you no longer need them.
* New: Live previews and adjustable settings for every module.
* New: Three separate button effects: Ripple Fill, Border Glow and Sliding Underline.
* New: Adobe Fonts support in Font Preconnect.
* Improved: Only one scroll animation can be active at a time, so effects never conflict.
* Improved: Every module class explains what it does and shows how many pages use it.

= 0.1.0 =
* Initial release: a library of ready-made Elementor classes and variables you can add, edit and reset, plus ten lightweight motion and interaction modules.
