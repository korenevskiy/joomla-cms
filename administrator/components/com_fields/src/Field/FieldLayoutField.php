<?php
/**
 * @package     Joomla.Administrator
 * @subpackage  com_fields
 *
 * @copyright   (C) 2005 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Joomla\Component\Fields\Administrator\Field;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Filesystem\Folder;
use Joomla\CMS\Filesystem\Path;
use Joomla\CMS\Form\FormField;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;

/**
 * Form Field to display a list of the layouts for a field from
 * the extension or template overrides.
 *
 * @since  3.9.0
 */
class FieldLayoutField extends FormField
{
	/**
	 * The form field type.
	 *
	 * @var    string
	 * @since  3.9.0
	 */
	protected $type = 'FieldLayout';

	/**
	 * Method to get the field input for a field layout field.
	 *
	 * @return  string   The field input.
	 *
	 * @since   3.9.0
	 */
	protected function getInput()
	{
		$extension = explode('.', $this->form->getValue('context'));
		$extension = $extension[0];

		if ($extension)
		{
			// Get the database object and a new query object.
			$db = Factory::getDbo();
			$query = $db->getQuery(true);

			// Build the query.
			$query->select('element, name')
				->from('#__extensions')
				->where('client_id = 0')
				->where('type = ' . $db->quote('template'))
				->where('enabled = 1');

			// Set the query and load the templates.
			$db->setQuery($query);
			$templates = $db->loadObjectList('element');

			// Prepare the grouped list
			$groups = array();

			// Add "Use Default"
			$items = array();
			$items[] = HTMLHelper::_('select.option', '', Text::_('JOPTION_USE_DEFAULT'));

			// Add Custom Options
			foreach (parent::getOptions() as $option)
			{
				$items[] = HTMLHelper::_('select.option', $option->value, $option->text);
			}

			// Add layout files Options
			$fieldName = $this->form->getValue('type');

			foreach (Folder::files(JPATH_PLUGINS . "/fields/$fieldName/tmpl", '.php') as $layout)
			{
				$items[] = HTMLHelper::_('select.option', $fieldName . ':' . $layout, Text::alt($layout, $fieldName));
			}

			$groups[]['items'] = $items;

			// Prepare array of component layouts
			$component_layouts = array();

			// Build the search paths for component layouts.
			$component_path = Path::clean(JPATH_SITE . '/components/' . $extension . '/layouts/field');

			// Add the layout options from the component path.
			if (is_dir($component_path) && ($component_layouts = Folder::files($component_path, '^[^_]*\.php$', false, true)))
			{
				// Create the group for the component
				$groups['_'] = array();
				$groups['_']['id'] = $this->id . '__';
				$groups['_']['text'] = Text::sprintf('JOPTION_FROM_COMPONENT');
				$groups['_']['items'] = array();

				foreach ($component_layouts as $i => $file)
				{
					// Add an option to the component group
					$value = basename($file, '.php');
					$component_layouts[$i] = $value;

					if ($value === 'render')
					{
						continue;
					}

					$groups['_']['items'][] = HTMLHelper::_('select.option', $value, $value);
				}
			}

			// Loop on all templates
			if ($templates)
			{
				foreach ($templates as $template)
				{
					$files = array();
					$template_paths = array(
						Path::clean(JPATH_SITE . '/templates/' . $template->element . '/html/layouts/' . $extension . '/field'),
						Path::clean(JPATH_SITE . '/templates/' . $template->element . '/html/layouts/com_fields/field'),
						Path::clean(JPATH_SITE . '/templates/' . $template->element . '/html/layouts/field'),
					);

					// Add the layout options from the template paths.
					foreach ($template_paths as $template_path)
					{
						if (is_dir($template_path))
						{
							$files = array_merge($files, Folder::files($template_path, '^[^_]*\.php$', false, true));
						}
					}

					foreach ($files as $i => $file)
					{
						$value = basename($file, '.php');

						// Remove the default "render.php" or layout files that exist in the component folder
						if ($value === 'render' || in_array($value, $component_layouts))
						{
							unset($files[$i]);
						}
					}

					if (count($files))
					{
						// Create the group for the template
						$groups[$template->name] = array();
						$groups[$template->name]['id'] = $this->id . '_' . $template->element;
						$groups[$template->name]['text'] = Text::sprintf('JOPTION_FROM_TEMPLATE', $template->name);
						$groups[$template->name]['items'] = array();

						foreach ($files as $file)
						{
							// Add an option to the template group
							$value = basename($file, '.php');
							$groups[$template->name]['items'][] = HTMLHelper::_('select.option', $value, $value);
						}
					}
				}
			}

			// Compute attributes for the grouped list
			$attr = $this->element['size'] ? ' size="' . (int) $this->element['size'] . '"' : '';
			$attr .= $this->element['class'] ? ' class="' . (string) $this->element['class'] . '"' : '';

			// Prepare HTML code
			$html = array();

			// Compute the current selected values
			$selected = array($this->value);

			// Add a grouped list
			$html[] = HTMLHelper::_(
				'select.groupedlist', $groups, $this->name,
				array('id' => $this->id, 'group.id' => 'id', 'list.attr' => $attr, 'list.select' => $selected)
			);

			return implode($html);
		}

		return '';
	}
}
