-- Move display_name from users to users_ig (one name per character).
-- Run once on the live database before deploying the code changes.
 
ALTER TABLE users_ig
    ADD COLUMN display_name VARCHAR(100) DEFAULT NULL AFTER id_user;

UPDATE users_ig UI
    INNER JOIN users U ON U.id_user = UI.id_user
SET UI.display_name = U.display_name
WHERE UI.display_name IS NULL OR UI.display_name = '';

-- Optional: remove account-level display name after code is deployed.
-- ALTER TABLE users DROP COLUMN display_name;
