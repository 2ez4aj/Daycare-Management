-- Add id_picture_path column to users table
ALTER TABLE users ADD COLUMN id_picture_path VARCHAR(500) NULL AFTER profile_photo_path;
