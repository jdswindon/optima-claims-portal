<div class="hf-small-margin">
	<div id="hf-field-builder"></div>
    <?php if ( ! defined( 'HF_PREMIUM_VERSION' ) ) : ?>
    <p class="hf-premium">
        <?php echo sprintf( __('Add a File Upload field with <a href="%s">HTML Forms Premium</a>', 'html-forms' ), 'https://htmlformsplugin.com/premium/#utm_source=wp-plugin&amp;utm_medium=html-forms&amp;utm_campaign=fields-tab' ); ?>.
    </p>
    <?php endif; ?>
</div>

<div class="hf-small-margin">
	<div class="hf-row">
		<div class="hf-col" style="min-width: 600px;">
			<h4 style="margin-bottom: 8px;">
                <label for="hf-form-editor">
                    <?php _e('Form Code', 'html-forms'); ?>
                    <a id="hf-show-form-preview" href="#hf-form-editor"><?php _e('Show Form Preview', 'html-forms'); ?></a>
                </label>
            </h4>
			<textarea id="hf-form-editor" class="widefat" name="form[markup]" cols="160" rows="20" autocomplete="false" autocorrect="false" autocapitalize="false" spellcheck="false"><?php echo htmlspecialchars($form->markup, ENT_QUOTES, get_option('blog_charset')); ?></textarea>
			<?php submit_button(); ?>
		</div>
		<div id="hf-form-preview-container" class="hf-col" style="min-width: 400px;">
			<h4 style="margin-bottom: 8px;">
                <labe lfor="hf-form-preview">
                    <?php _e('Form Preview', 'html-forms'); ?>
                </label>
                <a id="hf-hide-form-preview" href="#hf-form-editor"><?php _e('Hide Form Preview', 'html-forms'); ?></a>
            </h4>
			<iframe id="hf-form-preview" src="<?php echo esc_attr($form_preview_url); ?>"></iframe>
			<p class="description"><?php esc_html_e('The form may look slightly different than this when shown in a post, page or widget area.', 'html-forms'); ?></p>
		</div>
	</div>
</div>

<input type="hidden" id="hf-required-fields" name="form[settings][required_fields]" value="<?php echo esc_attr($form->settings['required_fields']); ?>" />
<input type="hidden" id="hf-email-fields" name="form[settings][email_fields]" value="<?php echo esc_attr($form->settings['email_fields']); ?>" />
