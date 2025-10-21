-- Check for Tabanan data
SELECT name, area, address 
FROM businesses 
WHERE area LIKE '%Tabanan%' 
LIMIT 5;

-- Check for Baturiti data  
SELECT name, area, address 
FROM businesses 
WHERE area LIKE '%Baturiti%' 
LIMIT 5;

-- Check for "Luar Bali" data
SELECT name, area, address 
FROM businesses 
WHERE area LIKE '%Luar Bali%' 
LIMIT 5;

-- Count totals
SELECT 
    COUNT(*) as total_tabanan
FROM businesses 
WHERE area LIKE '%Tabanan%';

SELECT 
    COUNT(*) as total_baturiti
FROM businesses 
WHERE area LIKE '%Baturiti%';

SELECT 
    COUNT(*) as total_luar_bali
FROM businesses 
WHERE area LIKE '%Luar Bali%';
