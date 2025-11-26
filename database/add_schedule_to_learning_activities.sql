-- Add schedule_id column to learning_activities table
ALTER TABLE learning_activities ADD COLUMN schedule_id INT NULL AFTER attachment_type;

-- Add foreign key constraint
ALTER TABLE learning_activities ADD CONSTRAINT fk_learning_activities_schedule 
FOREIGN KEY (schedule_id) REFERENCES schedules(id) ON DELETE SET NULL;

-- Add index for better performance
CREATE INDEX idx_learning_activities_schedule ON learning_activities(schedule_id);
