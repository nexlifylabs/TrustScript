=== TrustScript ===
Contributors: nexlifycreator, tssaini
Tags: woocommerce reviews, review plugin, product reviews, customer reviews, review reminders
Requires at least: 6.2
Tested up to: 6.9
Stable tag: 1.0.0
License: GPL-2.0+
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Requires PHP: 8.0

Collect, verify, and showcase WooCommerce product reviews with photos and videos. 
TrustScript helps you turn real customer feedback into high-impact social proof with 
a clean review UI, built-in verification, and a rich media gallery — no account required.

== Description ==

TrustScript is a modern WooCommerce review system that gives your store rich, 
conversion-focused social proof — photo reviews, video reviews, verified purchase 
badges, and a gallery UI — without sending a single byte of your customer data to 
an external server. Everything lives on your own WordPress installation, always.

Most review plugins trade your customer data for convenience. TrustScript doesn't. 
Your customers' personal information — names, emails, order history — never leaves 
your server, whether you're running in offline mode or connected to the TrustScript 
API. Full data ownership is not a paid feature. It's the foundation.

Choose **Simple Mode** to start collecting better reviews instantly with zero 
configuration, or activate **API Mode** to automate review requests, reminders, 
and post-purchase workflows at scale.

---

= Simple Review Collection - Free, No Account Required =

Simple mode lets you collect reviews manually without any external service or API key.
Send review request emails directly from the Analytics page, collect responses through a
hosted on-site form, and publish reviews to your product pages automatically or with
admin approval.

Simple mode features:

* Send review request emails manually from the Analytics page
* Single review link covers all products in an order
* Customizable email template with placeholder tags for order and store details
* Auto-publish or manual approval for submitted reviews
* Photo uploads - customers can attach up to 3 photos per review
* Opt-out link included in every review request email
* Smart void handling:
  - If an order is cancelled before the review request is sent, no email is triggered
  - If an order is cancelled or refunded after the email is sent, the review link
    automatically expires
  - For partial cancellations on multi-product orders, only eligible products appear
    in the review form

Note: Simple mode has its own email template and on-site review form. These are separate
from API mode and are not used when API mode is active.

---

= API Review Collection - Automate Everything =

API mode connects TrustScript to the TrustScript service at trustscript.io to fully
automate review collection and publishing. A free plan is available with 25 review
requests per month.

API mode features:

* Automated review request emails - sent automatically when an order reaches the
  configured trigger status (e.g. Delivered)
* Automated review publishing - reviews are pushed back to your WooCommerce product
  pages via webhook, with no manual steps required
* Customizable review form branding - match the form to your store's look and feel
* Custom subdomain for your review form
* Photo and video reviews - customers can attach up to 5 files; images are automatically
  compressed before upload
* AI writing assistant - optional, customer-initiated; offers 3 rewrite suggestions
  while preserving the customer's original words
* Up to 3 automated follow-up reminders for customers who haven't submitted yet
* Smart void handling - review links auto-expire on cancellation, refund, or partial
  cancellation, with per-product eligibility checks
* Opt-out link included in every review request email

A free plan is available with 25 review requests per month. The limit resets on the
1st of each month.

View all plans: https://trustscript.io/pricing
Full documentation: https://trustscript.io/docs

---

= Build Trust With Visual, Verified Reviews =

* Photo and video reviews displayed in a lightbox gallery on every product page
* Verified Purchase badge on every API-collected review - publicly checkable at
  trustscript.io/verify-review
* Star ratings and review counts on shop, category, and search pages
* Helpful voting so your best reviews rise to the top naturally

= Protect Privacy - Zero PII by Design =

TrustScript never collects, stores, or transmits customer names, email addresses, or any
personally identifiable information. Only a one-way hashed email (for opt-out) and an
order number (for verification) are ever used. Fully GDPR and CCPA compliant - not by
checkbox, but by architecture.

= Boost SEO - Automatically =

* JSON-LD structured data injected on every product page
* Star ratings appear directly in Google search results
* Fresh user-generated content added to your store with every published review

= Smart Analytics Dashboard =

* Track review requests sent, opened, converted, and abandoned
* Full per-order timeline from link creation to published review
* One-click manual reminders (Simple mode) or automated follow-ups (API mode)
* AI usage tracking - see how many reviews used the writing assistant (API mode)
* CSV export for all review data

= Works With Your Page Builder =

The Elementor widget lets you showcase reviews anywhere on your site in slider, grid, or
masonry layouts - updating automatically as new reviews are published.

---

= Trust Strip - Store Rating on Every Product Page =

The Trust Strip displays a summary bar on your product pages showing:

* Overall store star rating
* Total number of reviews
* Percentage of verified buyers
* Percentage of customers who recommend the store

The Trust Strip works with both Simple and API modes and can be toggled on or off from
TrustScript → Review Settings.

---

Key Features at a Glance:

* Simple On-Site Reviews - free, no account required; send emails manually and collect
  reviews directly on your site
* API Review Collection - fully automated review requests, publishing, and follow-ups
* Photo Reviews - up to 3 photos (Simple) or 5 photos and videos with auto-compression
  (API)
* Smart Void System - review links auto-expire on cancellation, refund, or partial
  cancellation
* Opt-Out Support - compliant opt-out link in every review request email
* Auto or Manual Publish - choose whether submitted reviews go live immediately or
  require admin approval
* AI Writing Assistant - optional, customer-initiated; available in API mode only
* Verified Purchase Badges - every API-collected review is tied to a real order
* Google Rich Snippets - JSON-LD structured data added automatically
* Helpful Voting - optional thumbs up/down voting on reviews (logged-in users only)
* Review Count on Product Cards - ratings and counts on shop, category, and search pages
* Elementor Widget - slider, grid, and masonry layouts
* Smart Refund Handling - review links auto-expire when orders are refunded or cancelled
* CSV Export - download all review data at any time
* Custom "Delivered" Order Status - ensures requests go out only after delivery
* Trust Strip - store rating summary bar on all product pages (both modes)

---

== External Services ==

= 1. TrustScript API (trustscript.io) - API Mode Only =

Used to register review requests, automate email delivery, verify purchases, and receive
completed reviews via webhook. Only required when API Review Collection is enabled.
Simple mode does not connect to any external service.

Service URL: https://trustscript.io
Privacy Policy: https://trustscript.io/privacy
Terms of Service: https://trustscript.io/terms

= Data Sent to TrustScript (API Mode) =

- Order number (used solely for purchase verification and analytics)
- One-way hashed email address (irreversible; used only for opt-out tracking)
- Webhook URL (where TrustScript posts the completed review back to your site)
- Source identifier (platform type, e.g. woocommerce)
- Rating, photo, and video collection flags
- Product name and order date (optional; can be disabled in settings)
- Product image URL (optional; sent if the product has an image)
- For multi-product orders: product name, product ID, product SKU, and a per-product
  token

Note: Customer review text and star rating are not sent by the plugin in the initial
request. They are collected by TrustScript's review form and returned to your site via
webhook after submission.

= What Is Never Sent =

- Customer names
- Email addresses in plain text
- Postal addresses or phone numbers
- Payment information
- Any other personally identifiable information

= Data Received from TrustScript (API Mode) =

Initial API response:
- Unique security token (for tracking and duplicate prevention)
- Duplicate flag (indicates if this order was already processed)
- Customer opted-out flag

Webhook (after customer submits their review):
- Final review text (customer-written, optionally AI-enhanced)
- Star rating (1–5)
- Verification token (for the public authenticity check page)
- Uploaded media URLs
- Verification hash (for webhook authenticity validation)

---

= 2. Microsoft Azure AI Foundry - Optional, API Mode Only =

When a customer clicks "Enhance", their draft review text is sent to Microsoft Azure AI
Foundry to generate rewrite suggestions. This is entirely optional and customer-initiated
- never triggered automatically.

Privacy Policy: https://privacy.microsoft.com
Terms of Service: https://azure.microsoft.com/en-us/support/legal/

By using this plugin, you agree to TrustScript's Privacy Policy:
https://trustscript.io/privacy

---

== Installation ==

= Simple Mode (No Account Required) =

1. Upload the plugin files to `/wp-content/plugins/trustscript/` or install directly
   from the WordPress plugin directory.
2. Activate the plugin through the Plugins menu in WordPress.
3. Navigate to TrustScript → Review Form and create a review page using the
   [trustscript_review_form] shortcode.
4. Select that page in the Review Form settings so TrustScript can link to it in emails.
5. Customize your email template with the available placeholder tags.
6. Navigate to TrustScript → Review Settings, set your trigger status and review request
   delay, and select Simple On-Site Reviews as your collection method.
7. Save settings. Review requests can now be sent manually from the Analytics page.

= API Mode =

1. Complete steps 1–2 above.
2. Sign up for a free TrustScript account at https://trustscript.io.
3. Get your API key from https://trustscript.io/dashboard/api-keys.
4. Navigate to TrustScript → Review Settings, enter your API key, and select
   API Review Collection as your collection method.
5. Configure your trigger status, review request delay, and any additional settings.
6. Save settings. Review requests will now be sent automatically.

Note: If you are using the Twenty Twenty-Five theme, the on-site review form styling may
not display correctly in Simple mode. For best results, use Twenty Twenty-Four or
Twenty Twenty-Three.

---

== Frequently Asked Questions ==

= Do I need a TrustScript account? =
No - not for Simple mode. Simple On-Site Reviews work entirely within your WordPress
site with no external account or API key required. A TrustScript account is only needed
for API mode, which unlocks automated sending, AI assistance, video reviews, and the
full analytics dashboard. A free plan is available with 25 review requests per month.

= What is the difference between Simple mode and API mode? =
Simple mode is free and requires no account. Review request emails are sent manually
from the Analytics page, and reviews are collected through an on-site form. API mode
automates the full review lifecycle - requests are triggered automatically when an order
reaches the configured status, and reviews are published to your product pages via
webhook. API mode also includes video reviews, AI writing assistance, automated
follow-ups, and custom branding.

= Can both modes be enabled at the same time? =
No. Simple mode and API mode use separate email templates and review collection flows.
Enabling API mode activates the premium feature set and replaces Simple mode.

= What is the Trust Strip? =
The Trust Strip is a store rating summary bar that appears on your product pages. It
displays your overall star rating, total review count, verified buyer percentage, and
recommendation rate. It works with both Simple and API modes and can be toggled on or
off from TrustScript → Review Settings.

= Is the plugin free? =
Yes, the plugin is free. Simple mode is fully free with no usage limits and no account
required. API mode includes a free plan with 25 review requests per month, with paid
plans for higher volume.

= Does this work without WooCommerce? =
TrustScript works with WooCommerce and MemberPress. At least one supported platform
must be installed and active.

= Are photo and video reviews supported? =
Simple mode supports up to 3 photo attachments per review. API mode supports up to 5
photos and videos, with automatic image compression so even large files upload quickly
without errors. Supported formats: JPG, PNG, WebP (auto-compressed); MP4 up to 100MB.
All media is displayed in a lightbox gallery on your product page.

= Can customers write their own reviews? =
Yes. In both modes, customers always write their own review. In API mode, the optional
AI writing assistant offers 3 rewrite suggestions if the customer chooses to use it -
their original words are always preserved and shown on the verification page.

= What happens if an order is cancelled or refunded? =
In both modes, the void system handles this automatically. If an order is cancelled
before the review request is sent, no email is triggered. If cancelled or refunded after
the email is sent, the review link expires immediately. For partial cancellations on
multi-product orders, only eligible products remain visible in the review form.

= Does TrustScript store personal customer data? =
No. TrustScript follows a strict Zero PII policy. The only data handled is the order
number (for verification) and a one-way hashed email address (for opt-out tracking).
This hash cannot be reversed. No names, email addresses, or personal identifiers are
ever stored on TrustScript servers.

= Is there a limit on how many reviews I can collect? =
Simple mode is unlimited - no account or quota applies. For API mode, the free plan
includes 25 review requests per month. Paid monthly plans include a quota for
AI-enhanced reviews (250, 500, or 2,000 depending on the plan). Annual subscribers
can continue collecting unlimited standard reviews after their AI quota is reached for
that month.

View all plans at https://trustscript.io/pricing

= Can I customize the review request email? =
Yes. In Simple mode, the email template is configured directly from TrustScript → Review
Form using the available placeholder tags. In API mode, email templates are managed from
your NexlifyLabs dashboard at trustscript.io with additional branding and customization
options.

Available placeholder tags (Simple mode):
{customer_name}, {customer_email}, {product_name}, {order_number}, {order_date},
{order_total}, {store_name}, {store_url}, {review_link}, {opt_out_link}

= Does TrustScript add a consent checkbox at checkout? =
By default, no - for most countries (including the USA) no consent checkbox is shown,
because TrustScript never collects personal data.

For customers in the EU, UK, Germany, and Austria, TrustScript automatically shows an
unchecked opt-in checkbox at checkout. German and Austrian customers go through a double
opt-in flow (checkbox + confirmation email) because German courts have classified review
request emails as marketing emails under UWG §7. The correct flow is applied
automatically based on billing country. Consent records are stored with timestamps.

= What happens if a customer doesn't tick the consent box? =
No review request email is sent. The order is marked as consent declined in TrustScript's
audit log and is permanently excluded from the review queue.

= Does TrustScript place any restrictions on email templates? =
Yes - and intentionally so. All templates are checked to ensure compliance with FTC and
GDPR guidelines. The following are not permitted:

- Incentive language (e.g. "discount", "coupon", "gift", "reward", "free", "cashback")
- Rating pressure (e.g. "5-star", "highest rating", "favorable review")
- Pressure language (e.g. "mandatory", "must review", "required")
- Review filtering (e.g. "only if you loved it", "satisfied customers only")

Templates must also include honest-feedback language - at least one of the words
"honest" or "feedback" must appear in the subject or body. Templates that violate these
rules cannot be saved until the issues are resolved.

= What is the "Delivered" order status? =
TrustScript adds a custom "Delivered" order status to WooCommerce to ensure review
requests go out only after customers have received their products - improving response
rates and review quality.

= Can customers verify that a review is real? =
Yes. Every API-collected review includes a Verified Purchase badge. Clicking it reveals
a unique verification token that can be checked at https://trustscript.io/verify-review
to confirm authenticity and view a full transparency log, including whether AI assistance
was used.

= Which page builders are supported? =
TrustScript includes a native Elementor widget for showcasing reviews in slider, grid,
or masonry layouts with automatic updates.

== Screenshots ==

1. Trust Strip - store rating summary bar on product pages
2. Analytics Dashboard - track requests, views, conversions, and per-order timelines
3. Review Settings - configure collection method, trigger status, and Trust Strip
4. Simple Mode - on-site review form powered by the [trustscript_review_form] shortcode
5. API Mode - customer review form with photo and video upload
6. AI Writing Assistant - choose from 3 suggestions or keep the original (API mode)
7. Verified Purchase Badge and Verification Token modal (API mode)
8. Public Review Verification page at trustscript.io/verify-review
9. Elementor Review Showcase widget

== Changelog ==

= 1.0.0 =
* Added Simple On-Site Reviews - collect unlimited reviews with no API key or external
  account required
* New [trustscript_review_form] shortcode for embedding the review form on any page
* Photo uploads in Simple mode - customers can attach up to 3 photos per review
* Customizable email template in Simple mode with full placeholder tag support
* Auto-publish or admin-approval setting for submitted reviews
* Smart void system extended to Simple mode - review links auto-expire on cancellation,
  refund, or partial cancellation; ineligible products are hidden from the review form
* Opt-out link support in Simple mode email templates
* Added Trust Strip - store rating summary bar (overall rating, total reviews, verified
  buyer %, recommendation %) displayed on product pages; toggle in Review Settings
* Review Collection Method setting - choose Simple On-Site Reviews or API Review
  Collection from TrustScript → Review Settings
* Review request delay setting - configurable delay with optional separate delay for
  international orders
* Full WooCommerce integration with automated review requests (API mode)
* Photo and video review support - up to 5 files per review with auto-compression
* Verified Purchase badges with public verification tokens
* Optional AI writing assistant with 3 variants per submission
* Zero PII collection - no names, emails, or identifiers stored
* Auto-saving drafts - customers can return and continue anytime
* Smart refund and cancellation handling - review links auto-expire
* Google Rich Snippets (JSON-LD) for star ratings in search results
* Helpful voting system (logged-in users only)
* Star ratings and review counts on all product cards
* Full analytics dashboard with per-order Review Insights
* Elementor widget - slider, grid, and masonry layouts
* Custom "Delivered" WooCommerce order status
* CSV export for all review data (From Backend Dashboard)

== Upgrade Notice ==

= 1.0.0 =
Initial public release of TrustScript for WooCommerce.

== Support ==

- Documentation: https://trustscript.io/docs
- Support Portal: https://trustscript.io/contact
- Email: support@trustscript.io
- Privacy: https://trustscript.io/privacy
- Terms of use: https://trustscript.io/terms