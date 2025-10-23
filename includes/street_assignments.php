<?php
/**
 * Street assignments for encoders
 * Divides 95 streets into 3 groups
 */

// Encoder 1: Streets A-J (32 streets)
$encoder1_streets = [
    'Aaron', 'Abraham', 'Adam', 'Almond St', 'Athena',
    'Babylonia', 'Bethel',
    'Camia', 'Camia St', 'Carmel', 'Carnation', 'Cattleya Rd', 'Colosse', 'Cornelius',
    'Dahlia', 'Daisy', 'Datu Puti', 'David', 'Diamond',
    'Earth', 'Elijah', 'Emerald', 'Ephesus', 'Ephraim', 'Everlasting',
    'Galathia', 'Garnet', 'Germanium', 'Gladiola',
    'Humabon',
    'Ipil St', 'Isaac',
    'Jacob', 'Jade St', 'Jasmin', 'Jenemiah'
];

// Encoder 2: Streets J-P (32 streets)
$encoder2_streets = [
    'Joseph', 'Joshua', 'Jupiter',
    'Kabiling', 'Kalantiaw', 'Kalayaan', 'Kapayapaan', 'Kingfisher', 'Kudarat', 'Kulambo', 'Kumintang',
    'Lakandula', 'Lapu-Lapu', 'Lilac', 'Lotus',
    'Magnolia', 'Maragtas', 'Maricudo', 'Mark', 'Mars', 'Matthew', 'Mercury', 'Minda Mora', 'Moses',
    'Narra', 'Nightingale', 'Noah',
    'Panday Pira', 'Paul'
];

// Encoder 3: Streets P-Z (31 streets)
$encoder3_streets = [
    'Pearl', 'Peter', 'Philip', 'Pine Street',
    'Quintos Villa',
    'Rosal', 'Rosas', 'Rose', 'Ruby',
    'Sampaguita', 'Samson', 'Samuel', 'Sapphire', 'Saturn', 'Siagu', 'Sikatuna Ave', 'Silver', 'Simeon', 'Sinai', 'Soliman', 'Star', 'Sumakwel', 'Sun',
    'Tarhata', 'Topaz',
    'Venus',
    'Yakal',
    'Zabarte Rd', 'Zenia'
];

/**
 * Get streets assigned to a specific encoder role
 */
function getStreetsForEncoder($role) {
    global $encoder1_streets, $encoder2_streets, $encoder3_streets;
    
    switch($role) {
        case 'encoder1':
            return $encoder1_streets;
        case 'encoder2':
            return $encoder2_streets;
        case 'encoder3':
            return $encoder3_streets;
        default:
            return array_merge($encoder1_streets, $encoder2_streets, $encoder3_streets);
    }
}

/**
 * Check if a street is assigned to a specific encoder
 */
function isStreetAssignedToEncoder($street, $role) {
    $assigned_streets = getStreetsForEncoder($role);
    return in_array($street, $assigned_streets);
}

/**
 * Get encoder role for a specific street
 */
function getEncoderForStreet($street) {
    global $encoder1_streets, $encoder2_streets, $encoder3_streets;
    
    if (in_array($street, $encoder1_streets)) {
        return 'encoder1';
    } elseif (in_array($street, $encoder2_streets)) {
        return 'encoder2';
    } elseif (in_array($street, $encoder3_streets)) {
        return 'encoder3';
    }
    
    return null;
}
?>
