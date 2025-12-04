-- Adds a published post that contains a Gutenberg image block referencing
-- the wastewater photo stored at /wp-content/uploads/2025/12/wastewater.jpg.
-- This migration is idempotent: it skips creation if the slug already exists.

SET @post_slug := 'wastewater-image-block';
SET @post_title := 'Wastewater Image Spotlight';
SET @image_path := '/wp-content/uploads/2025/12/wastewater.jpg';
SET @published_at := NOW();
SET @published_at_gmt := UTC_TIMESTAMP();
SET @existing_post_id := (
    SELECT ID FROM wp_posts WHERE post_name = @post_slug LIMIT 1
);

INSERT INTO wp_posts (
    ID,
    post_author,
    post_date,
    post_date_gmt,
    post_content,
    post_title,
    post_excerpt,
    post_status,
    comment_status,
    ping_status,
    post_password,
    post_name,
    to_ping,
    pinged,
    post_modified,
    post_modified_gmt,
    post_content_filtered,
    post_parent,
    guid,
    menu_order,
    post_type,
    post_mime_type,
    comment_count
) SELECT
    NULL,
    1,
    @published_at,
    @published_at_gmt,
    CONCAT(
        '<!-- wp:paragraph -->\n',
        '<p>Fresh sampling data highlights how wastewater monitoring informs upcoming capital projects.</p>\n',
        '<!-- /wp:paragraph -->\n\n',
        '<!-- wp:image {"sizeSlug":"full","linkDestination":"none"} -->\n',
        '<figure class="wp-block-image size-full"><img src="', @image_path, '" alt="Wastewater treatment facility" /></figure>\n',
        '<!-- /wp:image -->\n\n',
        '<!-- wp:paragraph -->\n',
        '<p>The featured image above lives at ', @image_path, ' so lower environments can reference the bundled asset.</p>\n',
        '<!-- /wp:paragraph -->'
    ),
    @post_title,
    '',
    'publish',
    'open',
    'open',
    '',
    @post_slug,
    '',
    '',
    @published_at,
    @published_at_gmt,
    '',
    0,
    CONCAT('https://example.com/?p=', @post_slug),
    0,
    'post',
    '',
    0
WHERE @existing_post_id IS NULL;

SET @new_post_id := COALESCE(@existing_post_id, LAST_INSERT_ID());

INSERT INTO wp_postmeta (post_id, meta_key, meta_value)
SELECT
    @new_post_id,
    '_wp_page_template',
    'default'
WHERE
    @new_post_id IS NOT NULL
    AND @new_post_id > 0
    AND NOT EXISTS (
        SELECT 1 FROM wp_postmeta
        WHERE post_id = @new_post_id
          AND meta_key = '_wp_page_template'
    );

