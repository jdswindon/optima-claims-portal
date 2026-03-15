<?php defined( 'ABSPATH' ) or exit;

$tabs = hf_get_admin_tabs($form);

?>
<script>document.title = 'Edit Form' + ' - ' + document.title;</script>
<div class="wrap hf">

    <p class="breadcrumbs">
        <span class="prefix"><?php echo __( 'You are here: ', 'html-forms' ); ?></span>
        <a href="<?php echo admin_url( 'admin.php?page=html-forms' ); ?>">HTML Forms</a> &rsaquo;
        <span class="current-crumb"><strong><?php _e( 'Edit Form', 'html-forms' ); ?></strong></span>
    </p>

    <h1 class="page-title"><?php _e( 'Edit Form', 'html-forms' ); ?></h1>

    <?php if ( ! empty( $_GET['saved'] ) ) {
        echo '<div class="notice notice-success"><p>' . __( 'Form updated.', 'html-forms' ) . '</p></div>';
    } ?>

    <form method="post">
        <input type="hidden" name="_hf_admin_action" value="save_form" />
		<input type="hidden" name="_wpnonce" value="<?php echo esc_attr( wp_create_nonce('_hf_admin_action') ); ?>" />
        <input type="hidden" name="form_id" value="<?php echo esc_attr( $form->ID ); ?>" />
        <input type="submit" style="display: none; " />

        <div id="titlediv">
            <div id="titlewrap">
                <label for="title"><?php _e( 'Form Title', 'html-forms' ); ?></label>
                <input type="text" name="form[title]" size="30" value="<?php echo esc_attr( $form->title ); ?>" id="title" spellcheck="true" autocomplete="off" placeholder="<?php echo __( "Enter the title of your form", 'html-forms' ); ?>" style="line-height: initial;" >
            </div>
        </div>
        
        <table class="form-table">

            <tr valign="top" class="hide-if-no-js">
                <th scope="row" style="width:80px;"><?php _e( 'Slug', 'html-forms' ); ?></th>
                <td>
                    <input type="text" class="regular-text" id="form-slug-input" name="form[slug]" value="<?php echo esc_attr( $form->slug ); ?>" readonly /> &lrm;<button type="button" class="button button-small" onclick="document.getElementById('form-slug-input').removeAttribute('readonly');" aria-label="<?php _e( 'Edit Slug', 'html-forms' ); ?>"><?php _e( 'Edit', 'html-forms' ); ?></button>
                </td>
            </tr>

            <tr valign="top">
                <th scope="row" style="width:80px;"><?php _e( 'Shortcode', 'html-forms' ); ?></th>
                <td>
                    <input type="text" class="regular-text" id="shortcode" value="<?php echo esc_attr( sprintf( '[hf_form slug="%s"]', $form->slug ) ); ?>" readonly onclick="this.select()">
                    <p class="description"><?php _e( 'Copy this shortcode and paste it into your post, page, or text widget content.', 'html-forms' ); ?></p>
                </td>
            </tr>

        </table>

        <div class="hf-small-margin">
            <h2 class="nav-tab-wrapper" id="hf-tabs-nav">
                <?php foreach( $tabs as $tab => $name ) {
                    $class = ( $active_tab === $tab ) ? 'nav-tab-active' : '';
                    echo sprintf( '<a class="nav-tab nav-tab-%s %s" data-tab-target="%s" href="%s">%s</a>', $tab, $class, $tab, $this->get_tab_url( $tab ), $name );
                } ?>
            </h2>

            <div id="tabs">
                <?php
                // output each tab
                foreach( $tabs as $tab => $name ) {
                    $class = ($active_tab === $tab) ? 'hf-tab-active' : '';
                    echo sprintf('<div class="hf-tab %s" id="tab-%s" data-tab="%s">', $class, $tab, $tab);
                    do_action( 'hf_admin_output_form_tab_' . $tab, $form );
                    echo '</div>';
                } // end foreach tab
                ?>

            </div><!-- / tabs -->
        </div>

    </form>

    <?php require __DIR__ . '/admin-footer.php'; ?>
</div>
