-- Migration: delete-wastewater-test-post
-- Date: 2025-12-11
-- Sequence: none (first migration of the day)

-- Add your SQL migration code here

delete from wp_posts where post_name = 'wastewater-image-block';
