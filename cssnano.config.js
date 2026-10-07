/*
 * wp-scripts' cssnano preset, plus colormin off: it rewrites colors to their
 * shortest form (rgba or #rrggbbaa to hsla), and the HSL rounding renders
 * 1 level off the design's colors.
 */
module.exports = {
	preset: [
		'default',
		{
			discardComments: { removeAll: true },
			colormin: false,
		},
	],
};
