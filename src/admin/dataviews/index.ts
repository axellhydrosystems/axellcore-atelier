// The DataViews/DataForm stylesheet, shared by the admin apps (members,
// resellers). Imported by each app, webpack emitted it only into the first
// entry's CSS, so it is its own entry, enqueued as their dependency
// (Assets::admin_dataviews_style()). Relative path on purpose: a bare
// '@wordpress/...' import would be rewritten by the WP dependency extraction
// into a script handle (wp-dataviews/...) that doesn't exist.
import '../../../node_modules/@wordpress/dataviews/build-style/style.css';
