-- Add columns for ID proof and child photo to users table
ALTER TABLE users
ADD COLUMN id_proof_path VARCHAR(500) NULL AFTER profile_photo_path,
ADD COLUMN child_photo_path VARCHAR(500) NULL AFTER id_proof_path;
