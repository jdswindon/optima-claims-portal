<?php 

// See: https://palmiak.github.io/timber-acf-wp-blocks/#/

// Using ACF Gutenberg Blocks package, look in this folder

add_filter( 'timber/acf-gutenberg-blocks-templates', function () {
  return ['_views/_blocks']; // default: ['views/blocks']
} );


// Disable all Gutenberg blocks, and then allow some defined ones

function filter_allowed_block_types_when_post_provided( $allowed_block_types, $editor_context ) {
    if ( ! empty( $editor_context->post ) ) {

      // Pre-defined Gutenberg blocks can be allowed here
      // For a list of all blocks, visit: https://wordpress.org/support/article/blocks/ or here https://gist.github.com/DavidPeralvarez/37c8c148f890d946fadb2c25589baf00 
      $allowedBlocks = array(
        // 'core/paragraph',
        // 'core/heading',
        // 'core/image',
        // 'core/gallery',
        // 'core/embed'
      );

      // Scan the directory for all our custom twig blocks
      $customBlocks = ( defined( 'WP_DEBUG' ) AND WP_DEBUG ) ? glob( __DIR__ . '/../_views/_blocks/*.twig', GLOB_ERR ) : glob( __DIR__ . '/../_views/_blocks/*.twig' );

      // For each of the twig files, add them to the allowedBlocks array
      foreach ($customBlocks as $block) {
        array_push($allowedBlocks, "acf/" . basename($block, ".twig"));
      }

      // And return the allowed blocks
      return $allowedBlocks;

    }

    return $allowed_block_types;
}
 
add_filter( 'allowed_block_types_all', 'filter_allowed_block_types_when_post_provided', 10, 2 );

// Add thumbnail to context for hero

add_filter( 'timber/acf-gutenberg-blocks-data/hero', function( $context ){
  $context['featured_image'] = get_the_post_thumbnail_url();
  return $context;
} );

// Add default parameters to blocks
// Commented out until tested further

// add_filter( 'timber/acf-gutenberg-blocks-default-data', function( $data ){
//   $data['default'] = array(
//       'post_type' => ['post','page'],
//   );
//   $data['pages'] = array(
//       'post_type' => 'page',
//   );
//   return $data;
// } );


// Add the title and icon of the block at the top of the block editor in WordPress.
add_action( 'acf/input/admin_head', 'display_acf_field_group_title_in_block' );
function display_acf_field_group_title_in_block() {
    // Run this only on block editor screens
    $current_screen = get_current_screen();

    if ( is_admin() && $current_screen && $current_screen->is_block_editor() ) {
        ?>
        <style>
            .acf-block-name {
                font-weight: 600;
                font-size: 1.3rem;
                padding: 12px 20px;
                margin: 0;
                background-color: #fff;
                border: #adb2ad solid 1px;
                border-bottom: 0;
                color: #444444;
                width: calc(100% - 42px);
                display: flex;
                align-items: center;
            }
            .acf-block-name .dashicon {
                margin-right: 0.5rem;
            }
        </style>
        <script type="text/javascript">
            wp.domReady(() => {
                const { select } = wp.data;
                const { subscribe } = wp.data;

                // Function to add an H2 title to each block
                const addBlockTitles = () => {
                    const blocks = document.querySelectorAll('.block-editor-block-list__block');
                    var acfData = acf.data.blockTypes;

                    blocks.forEach((block) => {
                        // Avoid duplicate titles by checking if an H2 already exists
                        if (!block.querySelector('.acf-block-name')) {
                            const blockName = block.dataset.title;
                            if(blockName) {
                                const title = document.createElement('div');
                                title.className = 'acf-block-name';
                                title.textContent = `${blockName}`;
                                title.style.color = '#333';

                                // Add the title at the top of the block
                                block.prepend(title);

                                var type = block.dataset.type;
                                acfData.forEach(function(data) {
                                    if(data.name == type) {
                                        var icon = document.createElement("span");
                                        var iconClass = data.icon;
                                        icon.classList.add('dashicon');
                                        icon.classList.add('dashicons');
                                        icon.classList.add('dashicons-' + iconClass);
                                        title.prepend(icon);
                                    }
                                });
                            }
                        }
                    });
                };

                // Initial run
                addBlockTitles();

                // Update titles whenever blocks are added/changed
                subscribe(() => {
                    addBlockTitles();
                });
            });
        </script>
        <?php
    }
}
