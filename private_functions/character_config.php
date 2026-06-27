<?php

if (!defined('ANIMASTER_MAX_CHARACTERS'))
{
    define('ANIMASTER_MAX_CHARACTERS', 5);
}

if (!defined('ANIMASTER_ASSET_VERSION'))
{
    define('ANIMASTER_ASSET_VERSION', '76');
}

function animaster_get_genders() 
{
    return ['M', 'F'];
}

function animaster_get_character_types() 
{
    return [
        'Actionhero', 'Astronaut', 'BasketballPlayer', 'Boxer', 'Business', 'Butler',
        'Carpenter', 'Casual', 'Chef', 'Claus', 'Clown', 'ConstructionWorker',
        'Cowboy', 'Cyclist', 'Dentist', 'Diving', 'Doctor', 'Eskimo', 'Explorer',
        'Farmer', 'Fire', 'Hazard', 'Judge', 'Knight', 'Lumberjack', 'Mechanic',
        'Metalhead', 'Mummy', 'Ninja', 'NavalOfficer', 'Paramedic', 'Pilot', 'Pirate',
        'Plumber', 'Police', 'Post', 'Prehistoric', 'Race', 'Reporter', 'Scientist',
        'Skater', 'Skeleton', 'Ski', 'Soldier', 'Sumo', 'Superhero', 'Swimsuit',
        'Tennis', 'Viking', 'Weightlifter', 'Wizard', 'Yeti', 'Zombie'
    ];
}

function animaster_is_valid_gender($gender)
{
    return in_array($gender, animaster_get_genders(), true);
}

function animaster_is_valid_character_type($character_type)
{
    return in_array($character_type, animaster_get_character_types(), true);
}
