<?php

/**
 * Add padding control to all blocks
 *
 * @author       Adtrak
 * @since        1.0.0
 * @license      GPL-2.0+
 **/

/**
 * Register padding control fields for all blocks except the Hero block
 * You must whitelist the classes you use in safelist.txt
 */
// add_action('acf/init', 'adtrak_register_block_padding_fields');
// function adtrak_register_block_padding_fields() {
    
//     // Register the field group
//     acf_add_local_field_group(array(
//         'key' => 'group_block_padding_controls',
//         'title' => 'Block Spacing Controls',
//         'fields' => array(
//             array(
//                 'key' => 'field_block_padding',
//                 'label' => 'Block Padding',
//                 'name' => 'block_padding',
//                 'type' => 'select',
//                 'instructions' => 'Adjust the spacing around this block',
//                 'required' => 0,
//                 'choices' => array(
//                     'default' => 'Default',
//                     'none' => 'No Padding',
//                     'remove_top_padding' => 'Remove Top Padding',
//                     'remove_bottom_padding' => 'Remove Bottom Padding',
//                 ),
//                 'default_value' => 'default',
//                 'allow_null' => 0,
//                 'multiple' => 0,
//                 'ui' => 1,
//                 'return_format' => 'value',
//             ),
//         ),
//         'location' => array(
//             array(
//                 array(
//                     'param' => 'block',
//                     'operator' => '==',
//                     'value' => 'all',
//                 ),
//                 array(
//                     'param' => 'block',
//                     'operator' => '!=',
//                     'value' => 'acf/hero',
//                 ),
//             ),
//         ),
//         'menu_order' => 99,
//         'position' => 'side',
//         'style' => 'default',
//         'label_placement' => 'top',
//         'instruction_placement' => 'label',
//         'hide_on_screen' => '',
//     ));
// }

// /**
//  * Add padding classes to block wrapper
//  */
// add_filter('timber/acf-gutenberg-blocks-data', 'adtrak_add_block_padding_classes', 10, 1);
// function adtrak_add_block_padding_classes($context) {
//     // Get the block padding value
//     $block_padding = get_field('block_padding');

//     // Set default classes
//     $padding_classes = '~py-10/28'; // Uses fluid-tailwind syntax for padding - change to any value you require

//     // Add classes based on the selected padding
//     if ($block_padding && $block_padding !== 'default') {
//         switch ($block_padding) {
//             case 'none':
//                 $padding_classes = '';
//                 break;
//             case 'remove_top_padding':
//                 $padding_classes = '~pb-10/28'; // Uses fluid-tailwind syntax for padding - change to any value you require
//                 break;
//             case 'remove_bottom_padding':
//                 $padding_classes = '~pt-10/28'; // Uses fluid-tailwind syntax for padding - change to any value you require
//                 break;
//         }
//     }

//     /* 
//         Add the padding classes to the context
//         This must be used in the block template to add the padding classes
//     */
//     $context['padding_classes'] = $padding_classes;
    
//     return $context;
// }