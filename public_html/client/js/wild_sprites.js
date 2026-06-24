/**
 * Low-res pixel sprites for wild animals (8x8), tinted by element color.
 */
var AnimasterWildSprites = (function ()
{
    var ARCH = {
        dog: [
            '........',
            '..333...',
            '.12221..',
            '12244321',
            '12244321',
            '.13331..',
            '.1..1...',
            '.2..2...'
        ],
        cat: [
            '3....3..',
            '33..33..',
            '.12221..',
            '12244321',
            '1223321.',
            '.13331..',
            '.1..1...',
            '........'
        ],
        snake: [
            '........',
            '....111.',
            '...1221.',
            '..1221..',
            '.1221...',
            '1221....',
            '.443....',
            '........'
        ],
        bat: [
            '333..333',
            '322..223',
            '.122221.',
            '..1441..',
            '..1221..',
            '...11...',
            '...22...',
            '........'
        ],
        bird: [
            '....3...',
            '...133..',
            '..12221.',
            '.1224431',
            '33333333',
            '..1221..',
            '..1..1..',
            '........'
        ],
        bird_tall: [
            '....3...',
            '...133..',
            '..12221.',
            '..1221..',
            '..1221..',
            '..1..1..',
            '..1..1..',
            '..2..2..'
        ],
        owl: [
            '........',
            '.344443.',
            '3411143.',
            '3411143.',
            '.122221.',
            '..1331..',
            '..1..1..',
            '........'
        ],
        penguin: [
            '....3...',
            '...133..',
            '..12221.',
            '..1221..',
            '..1221..',
            '..1221..',
            '..2..2..',
            '........'
        ],
        fish: [
            '........',
            '...111..',
            '..12221.',
            '.1224431',
            '..12221.',
            '...111..',
            '........',
            '........'
        ],
        whale: [
            '........',
            '..11111.',
            '.1224431',
            '122444431',
            '122444431',
            '..12221.',
            '...2.2..',
            '........'
        ],
        insect: [
            '....3...',
            '..32123.',
            '.122221.',
            '..1221..',
            '..1221..',
            '.1....1.',
            '2......2',
            '........'
        ],
        spider: [
            '1......1',
            '.1....1.',
            '..1221..',
            '.144441.',
            '..1221..',
            '.1....1.',
            '1......1',
            '........'
        ],
        rodent: [
            '........',
            '..333...',
            '.12221..',
            '12244321',
            '.12221..',
            '..1221..',
            '..2..2..',
            '........'
        ],
        hoofed: [
            '....3...',
            '...133..',
            '..12221.',
            '.122221.',
            '.122221.',
            '.1...1..',
            '.2...2..',
            '........'
        ],
        bear: [
            '3....3..',
            '33..33..',
            '.12221..',
            '12244321',
            '12244321',
            '1223321.',
            '.1...1..',
            '.2...2..'
        ],
        elephant: [
            '........',
            '..333...',
            '.12221..',
            '12244321',
            '12244321',
            '.12221..',
            '.1......',
            '.2......'
        ],
        ape: [
            '3....3..',
            '33..33..',
            '.12221..',
            '12244321',
            '.12221..',
            '.1221...',
            '.1..1...',
            '........'
        ],
        reptile: [
            '........',
            '...333..',
            '..12221.',
            '.1224431',
            '.122221.',
            '..1221..',
            '..2..2..',
            '........'
        ],
        frog: [
            '........',
            '.3...3..',
            '12244321',
            '12244321',
            '.12221..',
            '..1221..',
            '..2..2..',
            '........'
        ],
        crustacean: [
            '........',
            '1......1',
            '.122221.',
            '..1441..',
            '..1221..',
            '.1....1.',
            '2......2',
            '........'
        ],
        octopus: [
            '........',
            '..1221..',
            '.122221.',
            '.122221.',
            '.1..1.1.',
            '.1..1.1.',
            '........',
            '........'
        ],
        snail: [
            '........',
            '..3333..',
            '.122221.',
            '.122221.',
            '..1221..',
            '..1221..',
            '..2..2..',
            '........'
        ],
        butterfly: [
            '3....3..',
            '322223..',
            '.1221...',
            '..33....',
            '..33....',
            '.1221...',
            '322223..',
            '3....3..'
        ],
        dolphin: [
            '........',
            '....111.',
            '..12221.',
            '.1224431',
            '..12221.',
            '...2....',
            '........',
            '........'
        ],
        seal: [
            '........',
            '...133..',
            '..12221.',
            '.1224431',
            '.122221.',
            '..1..1..',
            '..2..2..',
            '........'
        ],
        pig: [
            '........',
            '..333...',
            '.12221..',
            '12244321',
            '12244321',
            '.12221..',
            '.1...1..',
            '.2...2..'
        ],
        kangaroo: [
            '....3...',
            '...133..',
            '..12221.',
            '..1221..',
            '..1221..',
            '..122...',
            '..2..2..',
            '........'
        ],
        quadruped: [
            '........',
            '...133..',
            '..12221.',
            '.122221.',
            '.122221.',
            '..1..1..',
            '..2..2..',
            '........'
        ]
    };

    var SPECIES_ARCH = {
        'Dog': 'dog',
        'Cat': 'cat',
        'Snake': 'snake',
        'Bat': 'bat',
        'Aardvark': 'quadruped',
        'Alligator': 'reptile',
        'Alpaca': 'hoofed',
        'Anaconda': 'snake',
        'Ant': 'insect',
        'Antelope': 'hoofed',
        'Ape': 'ape',
        'Aphid': 'insect',
        'Armadillo': 'quadruped',
        'Pithon': 'snake',
        'Ass': 'hoofed',
        'Baboon': 'ape',
        'Badger': 'quadruped',
        'Barracuda': 'fish',
        'Bass': 'fish',
        'Basset Hound': 'dog',
        'Bear': 'bear',
        'Beaver': 'rodent',
        'Bedbug': 'insect',
        'Bee': 'insect',
        'Beetle': 'insect',
        'Woodpecker': 'bird',
        'Bison': 'hoofed',
        'Black Panther': 'cat',
        'Black Widow Spider': 'spider',
        'Seagull': 'bird',
        'Blue Whale': 'whale',
        'Bobcat': 'cat',
        'Buffalo': 'hoofed',
        'Butterfly': 'butterfly',
        'King Penguin': 'penguin',
        'Camel': 'hoofed',
        'Caribou': 'hoofed',
        'Carp': 'fish',
        'Caterpillar': 'insect',
        'Catfish': 'fish',
        'Cheetah': 'cat',
        'Chicken': 'bird',
        'Chimpanzee': 'ape',
        'Chipmunk': 'rodent',
        'Cobra': 'snake',
        'Cod': 'fish',
        'Condor': 'bird',
        'Cougar': 'cat',
        'Cow': 'hoofed',
        'Coyote': 'dog',
        'Crab': 'crustacean',
        'Stork': 'bird_tall',
        'Cricket': 'insect',
        'Crocodile': 'reptile',
        'Crow': 'bird',
        'Deer': 'hoofed',
        'Komodo Dragon': 'reptile',
        'Dolphin': 'dolphin',
        'Donkey': 'hoofed',
        'Dragonfly': 'insect',
        'Duck': 'bird',
        'Eagle': 'bird',
        'Eel': 'fish',
        'Elephant': 'elephant',
        'Emu': 'bird_tall',
        'Falcon': 'bird',
        'Ferret': 'quadruped',
        'Snow Owl': 'owl',
        'Fish': 'fish',
        'Flamingo': 'bird_tall',
        'Flea': 'insect',
        'Fly': 'insect',
        'Fox': 'dog',
        'Frog': 'frog',
        'Goat': 'hoofed',
        'Goose': 'bird',
        'Gopher': 'rodent',
        'Gorilla': 'ape',
        'Grasshopper': 'insect',
        'Hamster': 'rodent',
        'Hare': 'rodent',
        'Hawk': 'bird',
        'Hippopotamus': 'quadruped',
        'Horse': 'hoofed',
        'Hummingbird': 'bird',
        'Humpback Whale': 'whale',
        'Iguana': 'reptile',
        'Impala': 'hoofed',
        'Kangaroo': 'kangaroo',
        'Ladybug': 'insect',
        'Leopard': 'cat',
        'Lion': 'cat',
        'Lizard': 'reptile',
        'Llama': 'hoofed',
        'Lobster': 'crustacean',
        'Mongoose': 'quadruped',
        'King Cobra': 'snake',
        'Monkey': 'ape',
        'Moose': 'hoofed',
        'Mosquito': 'insect',
        'Moth': 'insect',
        'Mountain goat': 'hoofed',
        'Mouse': 'rodent',
        'Mule': 'hoofed',
        'Octopus': 'octopus',
        'Orca': 'whale',
        'Ostrich': 'bird_tall',
        'Otter': 'quadruped',
        'Owl': 'owl',
        'Ox': 'hoofed',
        'Oyster': 'crustacean',
        'Panda': 'bear',
        'Parrot': 'bird',
        'Peacock': 'bird',
        'Pelican': 'bird',
        'Penguin': 'penguin',
        'Perch': 'fish',
        'Pheasant': 'bird',
        'Pig': 'pig',
        'Pigeon': 'bird',
        'Polar bear': 'bear',
        'Porcupine': 'rodent',
        'Quail': 'bird',
        'Rabbit': 'rodent',
        'Raccoon': 'quadruped',
        'Rat': 'rodent',
        'Viper': 'snake',
        'Great Grey Owl': 'owl',
        'Rhea': 'bird_tall',
        'Sea lion': 'seal',
        'Sheep': 'hoofed',
        'Shrew': 'rodent',
        'Skunk': 'quadruped',
        'Snail': 'snail',
        'Spider': 'spider',
        'Tiger': 'cat',
        'Walrus': 'seal',
        'Whale': 'whale',
        'Wolf': 'dog',
        'Zebra': 'hoofed',
        'Great Wolf': 'dog',
        'Dingo': 'dog',
        'Black Lynx': 'cat',
        'Turkey': 'bird',
        'Swan': 'bird',
        'Vulture': 'bird',
        'Love Bird': 'bird',
        'Hyacinth Macaw': 'bird',
        'Emperor Penguin': 'penguin'
    };

    var SPECIES_BY_ID = [
        '',
        'Dog', 'Cat', 'Snake', 'Bat', 'Aardvark', 'Alligator', 'Alpaca', 'Anaconda', 'Ant', 'Antelope',
        'Ape', 'Aphid', 'Armadillo', 'Pithon', 'Ass', 'Baboon', 'Badger', 'Barracuda', 'Bass', 'Basset Hound',
        'Bear', 'Beaver', 'Bedbug', 'Bee', 'Beetle', 'Woodpecker', 'Bison', 'Black Panther', 'Black Widow Spider', 'Seagull',
        'Blue Whale', 'Bobcat', 'Buffalo', 'Butterfly', 'King Penguin', 'Camel', 'Caribou', 'Carp', 'Caterpillar', 'Catfish',
        'Cheetah', 'Chicken', 'Chimpanzee', 'Chipmunk', 'Cobra', 'Cod', 'Condor', 'Cougar', 'Cow', 'Coyote',
        'Crab', 'Stork', 'Cricket', 'Crocodile', 'Crow', 'Deer', 'Komodo Dragon', 'Dolphin', 'Donkey', 'Dragonfly',
        'Duck', 'Eagle', 'Eel', 'Elephant', 'Emu', 'Falcon', 'Ferret', 'Snow Owl', 'Fish', 'Flamingo',
        'Flea', 'Fly', 'Fox', 'Frog', 'Goat', 'Goose', 'Gopher', 'Gorilla', 'Grasshopper', 'Hamster',
        'Hare', 'Hawk', 'Hippopotamus', 'Horse', 'Hummingbird', 'Humpback Whale', 'Iguana', 'Impala', 'Kangaroo', 'Ladybug',
        'Leopard', 'Lion', 'Lizard', 'Llama', 'Lobster', 'Mongoose', 'King Cobra', 'Monkey', 'Moose', 'Mosquito',
        'Moth', 'Mountain goat', 'Mouse', 'Mule', 'Octopus', 'Orca', 'Ostrich', 'Otter', 'Owl', 'Ox',
        'Oyster', 'Panda', 'Parrot', 'Peacock', 'Pelican', 'Penguin', 'Perch', 'Pheasant', 'Pig', 'Pigeon',
        'Polar bear', 'Porcupine', 'Quail', 'Rabbit', 'Raccoon', 'Rat', 'Viper', 'Great Grey Owl', 'Rhea', 'Sea lion',
        'Sheep', 'Shrew', 'Skunk', 'Snail', 'Spider', 'Tiger', 'Walrus', 'Whale', 'Wolf', 'Zebra',
        'Great Wolf', 'Dingo', 'Black Lynx', 'Turkey', 'Swan', 'Vulture', 'Love Bird', 'Hyacinth Macaw', 'Emperor Penguin'
    ];

    function clampByte(value)
    {
        return Math.max(0, Math.min(255, Math.round(value)));
    }

    function parseHexColor(hex)
    {
        var value = String(hex || '').trim();

        if (/^[0-9a-fA-F]{3,8}$/.test(value))
        {
            value = '#' + value;
        }

        if (!/^#[0-9a-fA-F]{6}$/.test(value))
        {
            return { r: 136, g: 136, b: 136 };
        }

        return {
            r: parseInt(value.slice(1, 3), 16),
            g: parseInt(value.slice(3, 5), 16),
            b: parseInt(value.slice(5, 7), 16)
        };
    }

    function rgbToHex(r, g, b)
    {
        function part(n)
        {
            var s = clampByte(n).toString(16);
            return s.length === 1 ? '0' + s : s;
        }

        return '#' + part(r) + part(g) + part(b);
    }

    function shade(hex, amount)
    {
        var rgb = parseHexColor(hex);

        return rgbToHex(
            rgb.r + (amount >= 0 ? (255 - rgb.r) * amount : rgb.r * amount),
            rgb.g + (amount >= 0 ? (255 - rgb.g) * amount : rgb.g * amount),
            rgb.b + (amount >= 0 ? (255 - rgb.b) * amount : rgb.b * amount)
        );
    }

    function makePalette(elementColor)
    {
        return {
            '1': elementColor,
            '2': shade(elementColor, -0.35),
            '3': shade(elementColor, 0.3),
            '4': '#1a1a1a',
            '5': '#f5f5f5'
        };
    }

    function resolveSpeciesKey(wild)
    {
        if (!wild)
        {
            return '';
        }

        if (wild.species_key)
        {
            return String(wild.species_key);
        }

        var id = parseInt(wild.id_species, 10);

        if (!isNaN(id) && SPECIES_BY_ID[id])
        {
            return SPECIES_BY_ID[id];
        }

        return String(wild.species || '');
    }

    function inferArchetype(speciesKey)
    {
        var name = String(speciesKey || '').toLowerCase();

        if (!name)
        {
            return 'quadruped';
        }

        if (name.indexOf('snake') !== -1 || name.indexOf('cobra') !== -1 || name.indexOf('viper') !== -1
            || name.indexOf('python') !== -1 || name.indexOf('pithon') !== -1 || name.indexOf('anaconda') !== -1)
        {
            return 'snake';
        }

        if (name.indexOf('whale') !== -1 || name === 'orca')
        {
            return 'whale';
        }

        if (name.indexOf('owl') !== -1)
        {
            return 'owl';
        }

        if (name.indexOf('penguin') !== -1)
        {
            return 'penguin';
        }

        if (name.indexOf('fish') !== -1 || name === 'bass' || name === 'cod' || name === 'carp'
            || name === 'perch' || name === 'eel' || name === 'barracuda')
        {
            return 'fish';
        }

        if (name.indexOf('spider') !== -1)
        {
            return 'spider';
        }

        if (name.indexOf('crab') !== -1 || name.indexOf('lobster') !== -1 || name.indexOf('oyster') !== -1)
        {
            return 'crustacean';
        }

        if (name.indexOf('bee') !== -1 || name.indexOf('ant') !== -1 || name.indexOf('fly') !== -1
            || name.indexOf('bug') !== -1 || name.indexOf('beetle') !== -1 || name.indexOf('moth') !== -1
            || name.indexOf('mosquito') !== -1 || name.indexOf('cricket') !== -1 || name.indexOf('grasshopper') !== -1
            || name.indexOf('caterpillar') !== -1 || name.indexOf('dragonfly') !== -1 || name.indexOf('flea') !== -1
            || name.indexOf('aphid') !== -1 || name.indexOf('ladybug') !== -1)
        {
            return 'insect';
        }

        if (name.indexOf('ostrich') !== -1 || name.indexOf('emu') !== -1 || name.indexOf('flamingo') !== -1
            || name.indexOf('stork') !== -1 || name.indexOf('rhea') !== -1)
        {
            return 'bird_tall';
        }

        if (name.indexOf('bird') !== -1 || name.indexOf('eagle') !== -1 || name.indexOf('hawk') !== -1
            || name.indexOf('duck') !== -1 || name.indexOf('chicken') !== -1 || name.indexOf('goose') !== -1
            || name.indexOf('swan') !== -1 || name.indexOf('turkey') !== -1 || name.indexOf('vulture') !== -1
            || name.indexOf('parrot') !== -1 || name.indexOf('macaw') !== -1 || name.indexOf('crow') !== -1
            || name.indexOf('pigeon') !== -1 || name.indexOf('pelican') !== -1 || name.indexOf('peacock') !== -1
            || name.indexOf('woodpecker') !== -1 || name.indexOf('seagull') !== -1 || name.indexOf('condor') !== -1
            || name.indexOf('falcon') !== -1 || name.indexOf('hummingbird') !== -1 || name.indexOf('quail') !== -1
            || name.indexOf('pheasant') !== -1)
        {
            return 'bird';
        }

        if (name.indexOf('lion') !== -1 || name.indexOf('tiger') !== -1 || name.indexOf('leopard') !== -1
            || name.indexOf('cat') !== -1 || name.indexOf('lynx') !== -1 || name.indexOf('cheetah') !== -1
            || name.indexOf('cougar') !== -1 || name.indexOf('panther') !== -1)
        {
            return 'cat';
        }

        if (name.indexOf('dog') !== -1 || name.indexOf('wolf') !== -1 || name.indexOf('fox') !== -1
            || name.indexOf('coyote') !== -1 || name.indexOf('dingo') !== -1)
        {
            return 'dog';
        }

        if (name.indexOf('mouse') !== -1 || name.indexOf('rat') !== -1 || name.indexOf('rabbit') !== -1
            || name.indexOf('hare') !== -1 || name.indexOf('hamster') !== -1 || name.indexOf('beaver') !== -1
            || name.indexOf('chipmunk') !== -1 || name.indexOf('porcupine') !== -1 || name.indexOf('shrew') !== -1
            || name.indexOf('gopher') !== -1)
        {
            return 'rodent';
        }

        if (name.indexOf('bear') !== -1 || name === 'panda')
        {
            return 'bear';
        }

        if (name.indexOf('elephant') !== -1)
        {
            return 'elephant';
        }

        if (name.indexOf('monkey') !== -1 || name.indexOf('ape') !== -1 || name.indexOf('gorilla') !== -1
            || name.indexOf('baboon') !== -1 || name.indexOf('chimp') !== -1)
        {
            return 'ape';
        }

        if (name.indexOf('frog') !== -1)
        {
            return 'frog';
        }

        if (name.indexOf('octopus') !== -1)
        {
            return 'octopus';
        }

        if (name.indexOf('snail') !== -1)
        {
            return 'snail';
        }

        if (name.indexOf('butterfly') !== -1)
        {
            return 'butterfly';
        }

        if (name.indexOf('dolphin') !== -1)
        {
            return 'dolphin';
        }

        if (name.indexOf('sea lion') !== -1 || name.indexOf('walrus') !== -1)
        {
            return 'seal';
        }

        if (name.indexOf('pig') !== -1)
        {
            return 'pig';
        }

        if (name.indexOf('kangaroo') !== -1)
        {
            return 'kangaroo';
        }

        if (name.indexOf('bat') !== -1)
        {
            return 'bat';
        }

        if (name.indexOf('croc') !== -1 || name.indexOf('gator') !== -1 || name.indexOf('lizard') !== -1
            || name.indexOf('iguana') !== -1 || name.indexOf('dragon') !== -1)
        {
            return 'reptile';
        }

        if (name.indexOf('horse') !== -1 || name.indexOf('cow') !== -1 || name.indexOf('sheep') !== -1
            || name.indexOf('goat') !== -1 || name.indexOf('deer') !== -1 || name.indexOf('zebra') !== -1
            || name.indexOf('camel') !== -1 || name.indexOf('llama') !== -1 || name.indexOf('alpaca') !== -1
            || name.indexOf('buffalo') !== -1 || name.indexOf('bison') !== -1 || name.indexOf('ox') !== -1
            || name.indexOf('donkey') !== -1 || name.indexOf('mule') !== -1 || name.indexOf('moose') !== -1
            || name.indexOf('antelope') !== -1 || name.indexOf('impala') !== -1 || name.indexOf('caribou') !== -1)
        {
            return 'hoofed';
        }

        return 'quadruped';
    }

    function getArchetype(wild)
    {
        var key = resolveSpeciesKey(wild);

        return SPECIES_ARCH[key] || inferArchetype(key);
    }

    function draw(ctx, cx, cy, wild, options)
    {
        options = options || {};

        var pixelSize = options.pixelSize || 2;
        var elementColor = options.elementColor || '#888888';
        var near = !!options.near;
        var flip = (parseInt(wild && wild.id_wild_animal, 10) || 0) % 2 === 1;
        var archetype = getArchetype(wild);
        var rows = ARCH[archetype] || ARCH.quadruped;
        var palette = makePalette(elementColor);
        var width = rows[0].length;
        var height = rows.length;
        var drawWidth = width * pixelSize;
        var drawHeight = height * pixelSize;
        var left = cx - drawWidth / 2;
        var top = cy - drawHeight / 2;

        if (near)
        {
            ctx.beginPath();
            ctx.arc(cx, cy, Math.max(drawWidth, drawHeight) / 2 + 4, 0, Math.PI * 2);
            ctx.strokeStyle = 'rgba(255, 255, 255, 0.55)';
            ctx.lineWidth = 2;
            ctx.stroke();
        }

        ctx.save();

        if (flip)
        {
            ctx.translate(cx, 0);
            ctx.scale(-1, 1);
            ctx.translate(-cx, 0);
        }

        for (var y = 0; y < height; y++)
        {
            for (var x = 0; x < width; x++)
            {
                var code = rows[y].charAt(x);

                if (code === '.')
                {
                    continue;
                }

                ctx.fillStyle = palette[code] || elementColor;
                ctx.fillRect(left + x * pixelSize, top + y * pixelSize, pixelSize, pixelSize);
            }
        }

        ctx.restore();
    }

    return {
        draw: draw,
        getArchetype: getArchetype,
        resolveSpeciesKey: resolveSpeciesKey
    };
})();
