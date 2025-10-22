-- Cleanup script to remove all users except admin
-- First, let's see what users exist
SELECT id, username, full_name, role, created_at FROM users ORDER BY id;

-- Count total users
SELECT COUNT(*) as total_users FROM users;

-- Delete all users except admin (username = 'admin' and role = 'admin')
DELETE FROM users WHERE username != 'admin' OR role != 'admin';

-- Show remaining users
SELECT id, username, full_name, role, created_at FROM users ORDER BY id;

-- Count remaining users
SELECT COUNT(*) as remaining_users FROM users;
