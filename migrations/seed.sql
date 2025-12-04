CREATE TABLE IF NOT EXISTS `wp_migrations` (
  `name` varchar(255) PRIMARY KEY,
  `applied_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO `wp_migrations` (`name`) VALUES
('seed.sql');
