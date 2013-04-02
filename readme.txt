=== Evaluate ===
Contributors: ebemunk, enej, ctlt-dev, ubcdev
Tags: rating, star rating, poll, vote, like
Requires at least: 3.3
Tested up to: 3.5
Stable tag: 1.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Evaluate adds many options for user interaction to your site, such as Like, Up/Down Vote, 5-Star ratings and polls.

== Description ==

Features:

* One Way (Like), Two Way (Up/Down Vote), Star (range out of 5) and Polls with multiple answers are supported
* Better rating/popularity sorting for metrics (Lower Bound of Wilson Score for Two-Way and Bayesian for 5-Star ratings)
* Multiple style options for one-way and two-way metrics
* Same metric for multiple content makes tracking metrics easier.
* AJAX voting and fetching support
* NodeJS & socket.io support (with CTLT_Stream plugin)
* Hooks into pre_get_posts to change sort order / criteria given URL parameters

Please check out FAQ for usage details!

== Installation ==

1. Install Evaluate by uploading the evaluate folder in your wp-content/plugins directory.
2. Activation for the first time will create the necessary tables for Evaluate to function.
3. Thats it! Evaluate supports Multisite too.

You will see Evaluate in the Settings section of the admin page.

== Frequently Asked Questions ==

= Creating and using metrics =

Metrics are what Evaluate uses to keep track of votes for content on your site (like posts, pages, or any public custome post type).
These can be questions like "Was this article helpful?", or be untitled and just have an internal name.
Metrics are usually associated with more than one content. They are displayed in order after the main post content.
Metrics will have a type and style associated with them. These are:

* One way
    * Thumbs
    * Arrow (Chevron)
    * Heart
* Two way
    * Thumbs
    * Arrow (Chevron)
* Range (5-Star Rating)
* Poll

Note: extending/customizing the styling options is quite easy (by just changing `img/sprite.gif` or through `css/evaluate.css`).

= Displaying/Hiding Metrics =

By default, Evaluate will display metrics for all content from the type you specified while creating the metric.
So, if you create a metric for 'pages', then that metric will be displayed on all content that is a 'page'.
If you want to exclude a particular metric from a specific post or page (or custom post type) you can use the Evaluate
meta box in the admin post area which lets you choose metrics that you want to exclude from that post.

= How are metrics scored? =

Evaluate scores the content that belong to a metric by user votes. For one-way (such as a Thumbs Up! for posts), this is just how many votes it has received.
For two-way (up/down vote) metrics, the lower bound of the [Wilson Score interval](http://en.wikipedia.org/wiki/Binomial_proportion_confidence_interval#Wilson_score_interval) is calculated.
This tries to better approach the 'real' ratio of up versus down votes than other methods as explained in [How Not to Sort by Average Rating](http://www.evanmiller.org/how-not-to-sort-by-average-rating.html).
Star rating scores are calculated by a simple Bayesian estimate (tending towards 2.5/5). Polls are not given a score, but their answers are of course expressed in percentages.

= Sorting content by metric score =

You can add the arguments `?evaluate=sort&metric_id=<my_id>` to any page that displays content to sort it by its Evaluate score. Adding `order=<asc|desc>` will determine the order.

== Screenshots ==

1. Metrics displayed underneath the post
2. Customizable styles
3. Admin main page
4. Metric details view

== Changelog ==

= 1.0 =
* Initial release