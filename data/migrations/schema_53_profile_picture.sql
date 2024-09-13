ALTER TABLE IF EXISTS lid_fotos
RENAME TO profile_pictures;

ALTER TABLE IF EXISTS profile_pictures
RENAME COLUMN lid_id TO member_id;

ALTER TABLE IF EXISTS profile_pictures
RENAME COLUMN foto TO photo;

ALTER TABLE IF EXISTS profile_pictures
RENAME COLUMN foto_mtime TO created_on;

ALTER TABLE IF EXISTS profile_pictures
ADD COLUMN reviewed boolean NOT NULL DEFAULT FALSE;

UPDATE profile_pictures
   SET reviewed = TRUE
 WHERE TRUE;

-- Delete any old photos we might currently be storing
DO $$
DECLARE
    v_photo record;
BEGIN
    FOR v_photo IN
        SELECT pp.member_id
              ,MAX(pp.created_on) max_created_on
          FROM profile_pictures AS pp
         GROUP BY pp.member_id
    LOOP
        DELETE FROM profile_pictures AS pp
        WHERE pp.member_id = v_photo.member_id
          AND pp.created_on < v_photo.max_created_on;
    END LOOP;
END $$;

-- Delete any photos from deleted accounts
DELETE FROM profile_pictures AS pp
 WHERE pp.member_id not in (SELECT id FROM leden);
