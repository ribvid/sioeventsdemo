const plugin = require('tailwindcss/plugin');
const postcss = require('postcss');
const postcssJs = require('postcss-js');

const clampGenerator = require('./resources/css-utils/clamp-generator.js');
const tokensToTailwind = require('./resources/css-utils/tokens-to-tailwind.js');

// Raw design tokens
const colorTokens = require('./resources/design-tokens/colors.json');
const fontTokens = require('./resources/design-tokens/fonts.json');
const spacingTokens = require('./resources/design-tokens/spacing.json');
const textSizeTokens = require('./resources/design-tokens/text-sizes.json');
const textLeadingTokens = require('./resources/design-tokens/text-leading.json');
const textTrackingTokens = require('./resources/design-tokens/text-tracking.json');
const textWeightTokens = require('./resources/design-tokens/text-weights.json');
const viewportTokens = require('./resources/design-tokens/viewports.json');

// Process design tokens
const colors = tokensToTailwind(colorTokens.items);
const fontFamily = tokensToTailwind(fontTokens.items);
const fontWeight = tokensToTailwind(textWeightTokens.items);
const fontSize = tokensToTailwind(clampGenerator(textSizeTokens.items));
const lineHeight = tokensToTailwind(textLeadingTokens.items);
const letterSpacing = tokensToTailwind(textTrackingTokens.items);
const spacing = tokensToTailwind(clampGenerator(spacingTokens.items));

const config = {
  content: ['./app/**/*.php', './resources/**/*.{html,php,vue,js}'],
  safelist: [],
  presets: [],
  theme: {
    screens: {
      sm: `${viewportTokens.min}px`,
      md: `${viewportTokens.mid}px`,
      lg: `${viewportTokens.large}px`,
      xl: `${viewportTokens.max}px`
    },
    colors,
    spacing,
    fontSize,
    lineHeight,
    letterSpacing,
    fontFamily,
    fontWeight,
    backgroundColor: ({theme}) => theme('colors'),
    textColor: ({theme}) => theme('colors'),
    fill: ({theme}) => theme('colors'),
    margin: ({theme}) => ({
      auto: 'auto',
      ...theme('spacing')
    }),
    padding: ({theme}) => theme('spacing')
  },
  variantOrder: [
    'first',
    'last',
    'odd',
    'even',
    'visited',
    'checked',
    'empty',
    'read-only',
    'group-hover',
    'group-focus',
    'focus-within',
    'hover',
    'focus',
    'focus-visible',
    'active',
    'disabled'
  ],

  // Disables Tailwind's reset and usage of rgb/opacity
  corePlugins: {
    preflight: false,
    textOpacity: false,
    backgroundOpacity: false,
    borderOpacity: false
  },

  // Prevents Tailwind's core components
  blocklist: ['container'],

  // Prevents Tailwind from generating that wall of empty custom properties
  experimental: {
    optimizeUniversalDefaults: true
  },

  plugins: [
    // Generates custom property values from tailwind config
    plugin(function ({addComponents, config}) {
      let result = '';

      const currentConfig = config();

      const groups = [
        {key: 'colors', prefix: 'color'},
        {key: 'spacing', prefix: 'space'},
        {key: 'fontSize', prefix: 'size'},
        {key: 'lineHeight', prefix: 'leading'},
        {key: 'letterSpacing', prefix: 'tracking'},
        {key: 'fontFamily', prefix: 'font'},
        {key: 'fontWeight', prefix: 'font'}
      ];

      groups.forEach(({key, prefix}) => {
        const group = currentConfig.theme[key];

        if (!group) {
          return;
        }

        Object.keys(group).forEach(key => {
          result += `--${prefix}-${key}: ${group[key]};`;
        });
      });

      if (result) {
        addComponents({
          ':root': postcssJs.objectify(postcss.parse(result))
        });
      }
    }),

    // Generates custom utility classes
    plugin(function ({addUtilities, config}) {
      const currentConfig = config();

      const customUtilities = [
        {key: 'spacing', prefix: 'flow-space', property: '--flow-space'},
        {key: 'spacing', prefix: 'gutter', property: '--gutter'},
        {key: 'spacing', prefix: 'repel-column-gap', property: '--repel-column-gap'},
        {key: 'spacing', prefix: 'repel-row-gap', property: '--repel-row-gap'},
        {key: 'spacing', prefix: 'switcher-column-gap', property: '--switcher-column-gap'},
        {key: 'spacing', prefix: 'switcher-row-gap', property: '--switcher-row-gap'},
        {key: 'spacing', prefix: 'cluster-column-gap', property: '--cluster-column-gap'},
        {key: 'spacing', prefix: 'cluster-row-gap', property: '--cluster-row-gap'},
      ];

      customUtilities.forEach(({key, prefix, property}) => {
        const group = currentConfig.theme[key];

        if (!group) {
          return;
        }

        Object.keys(group).forEach(key => {
          addUtilities({
            [`.${prefix}-${key}`]: postcssJs.objectify(
              postcss.parse(`${property}: ${group[key]}`)
            )
          });
        });
      });
    }),
  ],
};

export default config;
