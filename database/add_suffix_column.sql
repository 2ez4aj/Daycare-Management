-- Add suffix column to students table
-- This update adds support for name suffixes (Jr., Sr., II, III, etc.)

USE gumamela_daycare;

-- Add suffix column to students table
ALTER TABLE students 
ADD COLUMN suffix VARCHAR(10) NULL 
AFTER last_name;

-- Update any existing records to have empty suffix (optional)
UPDATE students SET suffix = '' WHERE suffix IS NULL;

-- Verify the change
DESCRIBE students;
