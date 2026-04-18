/**
 * Post Filter for Block Editor — editor.js
 *
 * Registers the block on the client side using wp.* globals.
 * No build step required; depends on editor script dependencies
 * declared in the PHP enqueue call.
 *
 * @package PostFilterBlockEditor
 */

( function ( blocks, element, blockEditor, components, serverSideRender, i18n ) {
    'use strict';

    var registerBlockType  = blocks.registerBlockType;
    var Fragment           = element.Fragment;
    var el                 = element.createElement;
    var InspectorControls  = blockEditor.InspectorControls;
    var useBlockProps      = blockEditor.useBlockProps;
    var ServerSideRender   = serverSideRender;
    var PanelBody          = components.PanelBody;
    var SelectControl      = components.SelectControl;
    var Notice             = components.Notice;
    var __                 = i18n.__;

    /**
     * Difficulty level dropdown options.
     *
     * @type {Array<{label: string, value: string}>}
     */
    var DIFFICULTY_OPTIONS = [
        { label: __( 'All Levels', 'post-filter-block-editor' ), value: '' },
        { label: __( 'Easy',       'post-filter-block-editor' ), value: 'easy' },
        { label: __( 'Medium',     'post-filter-block-editor' ), value: 'medium' },
        { label: __( 'Hard',       'post-filter-block-editor' ), value: 'hard' },
    ];

    /**
     * Block edit component.
     *
     * @param  {Object}   props             Block properties.
     * @param  {Object}   props.attributes  Current block attributes.
     * @param  {Function} props.setAttributes Attribute setter.
     * @return {Element}
     */
    function PostFilterEdit( props ) {
        var attributes    = props.attributes;
        var setAttributes = props.setAttributes;
        var blockProps    = useBlockProps( { className: 'pfbe-editor-preview' } );

        return el(
            Fragment,
            null,

            // ── Sidebar Inspector Controls ──────────────────────────────
            el(
                InspectorControls,
                null,
                el(
                    PanelBody,
                    {
                        title:       __( 'Filter Settings', 'post-filter-block-editor' ),
                        initialOpen: true,
                    },
                    el( SelectControl, {
                        label:   __( 'Default difficulty filter', 'post-filter-block-editor' ),
                        value:   attributes.selectedDifficulty,
                        options: DIFFICULTY_OPTIONS,
                        onChange: function ( newValue ) {
                            setAttributes( { selectedDifficulty: newValue } );
                        },
                    } )
                )
            ),

            // ── Live server-side preview in editor ──────────────────────
            el(
                'div',
                blockProps,
                el( ServerSideRender, {
                    block:      'post-filter-block-editor/posts-filter',
                    attributes: attributes,
                    LoadingResponsePlaceholder: function () {
                        return el(
                            'div',
                            { className: 'pfbe-editor-loading' },
                            __( 'Loading posts preview…', 'post-filter-block-editor' )
                        );
                    },
                } )
            )
        );
    }

    // ---------------------------------------------------------------------------

    registerBlockType( 'post-filter-block-editor/posts-filter', {

        /**
         * @see ./block.json
         * Metadata (title, icon, category, attributes, etc.) is loaded from
         * block.json via PHP register_block_type(); no need to repeat it here.
         */

        edit: PostFilterEdit,

        /**
         * Server-side rendering — save returns null.
         * All output is produced by pfbe_render_block() in PHP.
         *
         * @return {null}
         */
        save: function () {
            return null;
        },
    } );

}( 
    window.wp.blocks,
    window.wp.element,
    window.wp.blockEditor,
    window.wp.components,
    window.wp.serverSideRender,
    window.wp.i18n
) );
