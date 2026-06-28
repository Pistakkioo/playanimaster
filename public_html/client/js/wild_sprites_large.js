/**
 * Large wild sprites: native 32x32 pixel grid (see wild_sprites_arch_32.js).
 */
var AnimasterWildSprites = AnimasterWildSpritesFactory.create({
    arch: typeof AnimasterWildSpritesArch32 !== 'undefined' ? AnimasterWildSpritesArch32 : undefined,
    variant: 'large',
    defaultPixelSize: 1,
    nearPixelSize: 2
});
