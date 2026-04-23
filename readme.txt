=== FF Signature Field ===
Contributors: michaelkanda
Tags: fluent forms, signature, sign, digital signature, form
Requires at least: 5.6
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 2.0.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Adds a digital signature field to Fluent Forms. Users can sign directly in the form using mouse, touch, or stylus.

== Description ==

FF Signature Field is a lightweight add-on for [Fluent Forms](https://wordpress.org/plugins/fluentform/) that provides a canvas-based signature field. Users can draw their signature directly inside a form using a mouse, finger, or stylus.

**Features:**

* Canvas-based signature pad with instant drawing
* Works on desktop (mouse), mobile (touch), and tablet (stylus)
* Retina / HiDPI display support with automatic scaling
* Clear button to reset the signature
* Dashed baseline for visual guidance
* Optional required-field validation
* Signatures are saved as PNG images
* Compatible with conditional logic and multi-step forms
* Fully responsive – adapts to the form width automatically
* No external JavaScript libraries or CDN dependencies

**How it works:**

1. The signature is drawn on an HTML5 canvas element.
2. On form submission the drawing is serialised as a base64-encoded PNG.
3. The plugin saves the image to `wp-content/uploads/ff-signatures/` and stores the URL in the submission record.

== Installation ==

1. Upload the `ff-signature-field` folder to `wp-content/plugins/`.
2. Activate the plugin through the **Plugins** screen in WordPress.
3. Make sure Fluent Forms is installed and active.
4. Open a form in the Fluent Forms editor and drag the **Signature** field from the **Advanced Fields** section into your form.

== Frequently Asked Questions ==

= Does this plugin work without Fluent Forms? =

No. Fluent Forms must be installed and activated. The plugin will show an admin notice if Fluent Forms is missing.

= Can I have multiple signature fields in one form? =

Yes. Each signature field is independent and stores its own image.

= Where are the signature images stored? =

Images are saved under `wp-content/uploads/ff-signatures/{submission-id}/`.

= Does it work with conditional logic? =

Yes. You can configure conditional logic in the field settings just like any other Fluent Forms field.

= Does it work in multi-step forms? =

Yes. The JavaScript uses a MutationObserver to initialise signature fields that are injected into the DOM dynamically.

== Screenshots ==

1. The signature field in the Fluent Forms editor.
2. A user signing on a mobile device.
3. The saved signature in the submission details.

== Changelog ==

= 2.0.0 =
* Complete rewrite – custom canvas implementation without external libraries.
* JavaScript moved to a dedicated enqueued file.
* Improved compatibility with caching plugins and various themes.
* Added Retina / HiDPI support.
* Added MutationObserver for multi-step form compatibility.

= 1.0.0 =
* Initial release using the external signature_pad.js library.

== Upgrade Notice ==

= 2.0.0 =
Major rewrite. No external JS dependencies. Better theme and caching compatibility.

----------------------------------
Developed with ❤️ by Michael Kanda
https://designare.at


