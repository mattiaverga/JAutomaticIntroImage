<?php
/**
 * @copyright  Copyright (c) 2016- Mattia Verga. All rights reserved.
 * @license    GNU General Public License version 3, or later
 */
// no direct access
defined( '_JEXEC' ) or die;

class plgContentAutomaticIntroImage extends JPlugin
{
        /**
        * Load the language file on instantiation. Note this is only available in Joomla 3.1 and higher.
        * If you want to support 3.0 series you must override the constructor
        *
        * @var    boolean
        * @since  3.1
        */
        protected $autoloadLanguage = true;

        /**
        * Automatic creation of resized intro image from article full image
        *
        * @param   string   $context  The context of the content being passed to the
        plugin.
        * @param   mixed    $article  A reference to the JTableContent object that is 
        being saved which holds the article data.
        * @param   boolean  $isNew    A boolean which is set to true if the content
        is about to be created.
        *
        * @return  boolean	True on success.
        */
        public function onContentBeforeSave($context, $article, $isNew)
        {
                // Check if we're saving an article
                $allowed_contexts = array('com_content.article');

                if (!in_array($context, $allowed_contexts))
                {
                        return true;
                }
                
                $images = json_decode($article->images);
                
                // Check ImageMagick
                if (!extension_loaded('imagick'))
                {
                        JFactory::getApplication()->enqueueMessage(JText::_('PLG_CONTENT_AUTOMATICINTROIMAGE_MESSAGE_IMAGICK_ERROR'), 'error');
                        return true;
                }

                // Return if full article image is not set or empty
                if (!isset($images->image_fulltext) or empty($images->image_fulltext))
                {
                        return true;
                }
                
                // Return if intro image is already set
                if (isset($images->image_intro) and !empty($images->image_intro))
                {
                        JFactory::getApplication()->enqueueMessage(JText::_('PLG_CONTENT_AUTOMATICINTROIMAGE_MESSAGE_ALREADY_SET'), 'notice');
                        return true;
                }
                
               
                $width = (int)$this->params->get('Width');
                $height = (int)$this->params->get('Height');
                $compression_level = (int)$this->params->get('ImageQuality');
                
                // Check plugin settings
                if ($compression_level < 50 OR $compression_level > 100 OR
                    $width < 10 OR $width > 2000 OR
                    $height < 10 OR $height > 2000)
                {
                        JFactory::getApplication()->enqueueMessage(JText::_('PLG_CONTENT_AUTOMATICINTROIMAGE_MESSAGE_SETTINGS_ERROR'), 'error');
                        return true;
                }
                
                // Create resized image
                $thumb = new Imagick(JPATH_ROOT . '/' . $images->image_fulltext);
                
                $thumb->resizeImage($width,
                                    $height,
                                    Imagick::FILTER_LANCZOS,
                                    1,
                                    $this->params->get('MaintainAspectRatio')
                                    );
                if ($this->params->get('ChangeImageQuality') == 1)
                {
                    $thumb->setImageCompressionQuality($compression_level);
                }
                
                if ($this->params->get('SetProgressiveJPG') == 1)
                {
                    $thumb->setInterlaceScheme(Imagick::INTERLACE_PLANE);
                }
                
                // Get real image dimensions if maintain aspect ratio was selected
                if ($this->params->get('MaintainAspectRatio') == 1)
                {
                    $width = $thumb->getImageWidth();
                    $height = $thumb->getImageHeight();
                }
                
                // Set image intro name
                // {width} and {height} placeholders are changed to values
                $suffix = $this->params->get('Suffix');
                if (strpos($suffix, "{width}") !== false or
                    strpos($suffix, "{height}") !== false)
                {
                    $suffix = str_replace(array("{width}","{height}"),
                                          array($width,$height),
                                          $suffix);
                }
                $extension_pos = strrpos($images->image_fulltext, '.');
                $images->image_intro = substr($images->image_fulltext, 0, $extension_pos) . 
                                        $suffix . 
                                        substr($images->image_fulltext, $extension_pos);
                
                // Put the image in a subdir if set to do so
                if ($this->params->get('PutInSubdir') == 1)
                {
                    $subdir_pos = strrpos($images->image_intro, '/');
                    $images->image_intro = substr($images->image_intro, 0, $subdir_pos) . 
                                        '/' . $this->params->get('Subdir') .
                                        substr($images->image_intro, $subdir_pos);
                
                    // Check if the subdir already exist or create it
                    $img_subdir = JPATH_ROOT . '/' . substr($images->image_intro, 0, strrpos($images->image_intro, '/'));
                    if (!JFolder::exists($img_subdir))
                    {
                        JFolder::create($img_subdir);
                    }
                }
                
                // Copy Alt and Title fields
                if ($this->params->get('CopyAltTitle') == 1 and 
                    ($images->image_fulltext_alt != "" or 
                    $images->image_fulltext_caption != ""))
                {
                    $images->image_intro_alt = $images->image_fulltext_alt;
                    $images->image_intro_caption = $images->image_fulltext_caption;
                }
                
                // Write resized image if it doesn't exist
                // and set Joomla object values
                if (!file_exists(JPATH_ROOT . '/' . $images->image_intro))
                {
                    $thumb->writeImage(JPATH_ROOT . '/' . $images->image_intro);
                    JFactory::getApplication()->enqueueMessage(JText::sprintf('PLG_CONTENT_AUTOMATICINTROIMAGE_MESSAGE_CREATED', $images->image_intro), 'message');
                }
                else
                {
                    JFactory::getApplication()->enqueueMessage(JText::sprintf('PLG_CONTENT_AUTOMATICINTROIMAGE_MESSAGE_EXIST', $images->image_intro), 'message');
                }
                
                $article->images = json_encode($images);
                
                $thumb->destroy();
                
                return true;
        }
        
}
?>
