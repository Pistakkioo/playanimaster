/**
 * Wild animal spawn checks (web port of SpawnManager / SpawnPoint).
 */
var AnimasterSpawn = (function ()
{
    var CHECK_INTERVAL_MS = 10000;

    var playerRef = null;
    var spawnPoints = [];
    var lastGlobalCheck = 0;
    var lastCheckByPoint = {};
    var busy = false;
    var onSpawnCheckedCallback = null;

    function init(options)
    {
        onSpawnCheckedCallback = options && options.onSpawnChecked
            ? options.onSpawnChecked
            : null;
    }

    function setPlayer(player)
    {
        playerRef = player;
    }

    function reset()
    {
        spawnPoints = [];
        lastGlobalCheck = 0;
        lastCheckByPoint = {};
        busy = false;
    }

    function loadSpawnPoints()
    {
        if (!playerRef)
        {
            return Promise.resolve([]);
        }

        return AnimasterApi.getSpawnPoints(playerRef).then(function (rows)
        {
            spawnPoints = rows || [];
            return spawnPoints;
        }).catch(function (err)
        {
            console.warn('[AnimasterSpawn] load spawn points failed:', err && err.message ? err.message : err);
            spawnPoints = [];
            return [];
        });
    }

    function isPlayerInside(spawnPoint)
    {
        if (!playerRef || !spawnPoint)
        {
            return false;
        }

        var radius = parseFloat(spawnPoint.radius) || 0;
        var sx = parseFloat(spawnPoint.x);
        var sz = parseFloat(spawnPoint.z);

        if (radius <= 0)
        {
            return false;
        }

        return AnimasterWorld.distance(playerRef.x, playerRef.z, sx, sz) < radius;
    }

    function shouldCheckPoint(idSpawnPoint, now)
    {
        var last = lastCheckByPoint[idSpawnPoint] || 0;

        if (now - last < CHECK_INTERVAL_MS)
        {
            return false;
        }

        lastCheckByPoint[idSpawnPoint] = now;
        return true;
    }

    function tick(force)
    {
        if (!playerRef || busy || !spawnPoints.length)
        {
            return Promise.resolve();
        }

        var now = performance.now();

        if (!force && now - lastGlobalCheck < CHECK_INTERVAL_MS)
        {
            return Promise.resolve();
        }

        lastGlobalCheck = now;

        var toCheck = [];

        spawnPoints.forEach(function (spawnPoint)
        {
            var id = parseInt(spawnPoint.id_spawn_point, 10) || 0;

            if (!id || !isPlayerInside(spawnPoint))
            {
                return;
            }

            if (force || shouldCheckPoint(id, now))
            {
                toCheck.push(id);
            }
        });

        if (!toCheck.length)
        {
            return Promise.resolve();
        }

        busy = true;
        var spawned = false;

        var chain = Promise.resolve();

        toCheck.forEach(function (idSpawnPoint)
        {
            chain = chain.then(function ()
            {
                return AnimasterApi.checkSpawn(playerRef, idSpawnPoint).then(function ()
                {
                    spawned = true;
                }).catch(function (err)
                {
                    console.warn('[AnimasterSpawn] check spawn failed:', err && err.message ? err.message : err);
                });
            });
        });

        return chain.finally(function ()
        {
            busy = false;

            if (spawned && onSpawnCheckedCallback)
            {
                onSpawnCheckedCallback();
            }
        });
    }

    function getSpawnPoints()
    {
        return spawnPoints.slice();
    }

    return {
        init: init,
        setPlayer: setPlayer,
        reset: reset,
        loadSpawnPoints: loadSpawnPoints,
        tick: tick,
        getSpawnPoints: getSpawnPoints,
        isPlayerInside: isPlayerInside
    };
})();
