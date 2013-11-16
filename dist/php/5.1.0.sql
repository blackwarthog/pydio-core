ALTER TABLE `ajxp_feed` ADD COLUMN `index_path` MEDIUMTEXT NULL;

ALTER TABLE `ajxp_simple_store` ADD COLUMN `insertion_date` DATETIME DEFAULT CURRENT_TIMESTAMP;

CREATE TABLE IF NOT EXISTS ajxp_user_teams {
    team_id VARCHAR(255) NOT NULL,
    user_id varchar(255) NOT NULL,
    team_label VARCHAR(255) NOT NULL,
    owner_id varchar(255) NOT NULL,
    PRIMARY KEY(team_id, user_id)
}