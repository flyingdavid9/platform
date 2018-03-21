<?php defined('SYSPATH') or die('No direct script access');

/**
 * Ushahidi PostSet Listener
 *
 * Listens for new posts that are added to a set
 *
 * @author     Ushahidi Team <team@ushahidi.com>
 * @package    Ushahidi\Application
 * @copyright  2014 Ushahidi
 * @license    https://www.gnu.org/licenses/agpl-3.0.html GNU Affero General Public License Version 3 (AGPL3)
 */

use League\Event\AbstractListener;
use League\Event\EventInterface;
use \Ushahidi\Core\Entity\FormRepository;
use \Ushahidi\Core\Entity\ContactRepository;
use \Ushahidi\Core\Entity\PostRepository;
use \Ushahidi\Core\Entity\MessageRepository;
use \Ushahidi\Core\Entity\FormAttributeRepository;
use \Ushahidi\Core\Entity\ContactPostStateRepository;

class Ushahidi_Listener_ContactListener extends AbstractListener
{
	protected $repo;
	protected $post_repo;
	protected $form_repo;
	protected $message_repo;
	protected $form_attribute_repo;
	protected $contact_post_state;

	public function setRepo(ContactRepository $repo)
	{
		$this->repo = $repo;
	}


	public function setPostRepo(PostRepository $repo)
	{
		$this->post_repo = $repo;
	}

	public function setFormRepo(FormRepository $repo)
	{
		$this->form_repo = $repo;
	}

	public function setMessageRepo(MessageRepository $repo)
	{
		$this->message_repo = $repo;
	}

	public function setFormAttributeRepo(FormAttributeRepository $repo)
	{
		$this->form_attribute_repo = $repo;
	}

	public function setContactPostStateRepo(ContactPostStateRepository $repo)
	{
		$this->contact_post_state = $repo;
	}

	public function handle(EventInterface $event, $contactIds = null , $form_id = null, $event_type = null)
	{
		$result = [];
		foreach ($contactIds as $contactId) {
			$formEntity = $this->form_repo->get($form_id);
			$contactEntity = $this->repo->get($contactId);

			/**
			 * Create a new Post record per contact (related to the current survey/form_id).
			 * Each post has an autogenerated Title+Description based on contact and survey name
			 */
			$post = $this->post_repo->getEntity();
			$postState = array(
				'title' => "{$formEntity->name} - {$contactEntity->contact}",
				'content' => "{$formEntity->name} - {$contactEntity->contact}",
				'form_id' => $form_id,
				'status' => 'draft'
			);
			$post->setState($postState);
			$postId = $this->post_repo->create($post);
			/**
			 *  Create the first message (first survey question) for each contact.
			 *  Use the message status to mark it as "pending" (ready for delivery via SMS)
			 */
			$message = $this->message_repo->getEntity();
			$firstAttribute = $this->form_attribute_repo->getFirstByForm($form_id);
			if (!$firstAttribute->id) {
				//fixme add decent exception and log it
				throw new Exception('If this happens it means that the form does not have attributes so we can\'t send messages');
			}
			$messageState = array(
				'contact_id' => $contactId,
				'post_id' => $postId,
				'title' => $firstAttribute->label,
				'message' => $firstAttribute->label,
				'status' => 'pending',
			);
			$message->setState($messageState);
			$messageId = $this->message_repo->create($message);
			//contact post state
			$contactPostState = $this->contact_post_state->getEntity();
			$contactPostState->setState(array('post_id' => $postId, 'contact_id' => $contactId, 'status' => 'pending'));

			$contactPostStateId = $this->contact_post_state->create($contactPostState);
			$result[] = $contactPostStateId;
		}
		return $result;
	}

}
