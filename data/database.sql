CREATE TABLE `voices` (
  `id` int NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `name` varchar(255) NOT NULL,
  `text` text NOT NULL,
  `voice` varchar(255) NOT NULL,
  `file` varchar(255) NOT NULL,
  `line` int NOT NULL
);

ALTER TABLE `voices` ADD INDEX `voice` (`voice`);

CREATE TABLE `names` (
  `id` int NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `japanese` varchar(255) NOT NULL,
  `english` varchar(255) NOT NULL,
  `color` varchar(10) NOT NULL,
  `color_mg` varchar(10)
);

ALTER TABLE `names` ADD INDEX `japanese` (`japanese`);
