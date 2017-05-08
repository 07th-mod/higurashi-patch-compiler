CREATE TABLE `voices` (
  `id` int NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `name` varchar(255) NOT NULL,
  `text` text NOT NULL,
  `voice` varchar(255) NOT NULL,
  `file` varchar(255) NOT NULL,
  `line` int NOT NULL
);
