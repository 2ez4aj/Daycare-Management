-- Add schedule_id column to events table
ALTER TABLE events ADD COLUMN schedule_id INT NULL AFTER location;

-- Add foreign key constraint
ALTER TABLE events ADD CONSTRAINT fk_events_schedule 
FOREIGN KEY (schedule_id) REFERENCES schedules(id) ON DELETE SET NULL;

-- Add index for better performance
CREATE INDEX idx_events_schedule ON events(schedule_id);
