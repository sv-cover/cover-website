<?php
namespace App\Controller;

require_once 'src/framework/controllers/ControllerCRUD.php';

use App\Form\ProfilePictureType;
use Symfony\Component\Cache\Adapter\FilesystemTagAwareAdapter;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Contracts\Cache\ItemInterface;

class ProfilePictureController extends \ControllerCRUD
{
	// const FORMAT_ORIGINAL = 'portrait';
	// const FORMAT_SQUARE = 'square';

	const FORMAT_ORIGINAL = 'original';
	const FORMAT_PORTRAIT = 'portrait';
	const FORMAT_SQUARE = 'square';

	const MAX_WIDTH = 2000;

	protected $view_name = 'profilepictures';
	protected $form_type = ProfilePictureType::class;
	private $member_model;

	public function __construct($request, $router)
	{
		$this->model = get_model('DataModelProfilePicture');
		$this->member_model = get_model('DataModelMember');

		parent::__construct($request, $router);
	}

	public function path(string $view, \DataIter $iter = null)
	{
		$parameters = [
			'view' => $view,
		];

		if ($view === 'index')
			return $this->generate_url('profile_pictures.list', $parameters);

		if (isset($iter))
			$parameters['id'] = $iter->get_id();

		return $this->generate_url('profile_pictures', $parameters);
	}

	public function new_iter()
	{
		$iter = parent::new_iter();
		$iter->set('member_id', $this->get_parameter('member_id', \get_identity()->get('id')));
		return $iter;
	}

	protected function get_cache()
	{
		$directory = get_config_value('path_to_cache', sys_get_temp_dir());
		return new FilesystemTagAwareAdapter('profile_pictures', 0, $directory);
	}

	protected function get_format()
	{
		$format = $this->get_parameter('format');
		if (in_array($format, [self::FORMAT_SQUARE, self::FORMAT_PORTRAIT, self::FORMAT_ORIGINAL]))
			return $format;
		return self::FORMAT_SQUARE;
	}

	protected function get_width()
	{
		return min(intval($this->get_parameter('width') ?? self::MAX_WIDTH), self::MAX_WIDTH);
	}

	/**
	 * Serve an image with the correct headers to make caching possible.
	 */
	protected function serve_image(string $image, ?string $last_modified = null)
	{
		header('Pragma: public');
		header('Cache-Control: max-age=86400');
		header('Expires: '. gmdate('D, d M Y H:i:s \G\M\T', time() + 86400));

		$type = (new \finfo(\FILEINFO_MIME_TYPE))->buffer($image);
		if ($type !== null)
			header(sprintf('Content-Type: %s', $type));

		if ($last_modified !== null)
			header('Last-Modified: ' . $last_modified);

		echo $image;
	}

	/**
	 * Inform the browser nothing has changed since last time.
	 * Function name is inconsistent with serve_image, but conistent with other
	 * error codes on \Controller (e.g. run_404_not_found).
	 */
	protected function run_304_not_modified()
	{
		header('Pragma: public');
		header('Cache-Control: max-age=86400');
		http_response_code(304);
	}

	protected function _generate_original(\DataIterProfilePicture $iter)
	{
		$photo = $iter->get_stream();

		$imagick = new \Imagick();
		$imagick->readImageFile($photo['photo']);

		// Fix orientation, remove exif data
		apply_image_orientation($imagick);
		strip_exif_data($imagick);

		// Write the image as a progressive JPEG
		$imagick->setImageFormat('jpeg');
		$imagick->setInterlaceScheme(\Imagick::INTERLACE_PLANE);

		return $imagick->getImageBlob();
	}

	protected function _generate_scaled(\DataIterProfilePicture $iter, string $format, int $width)
	{
		$photo = $iter->get_stream();

		$imagick = new \Imagick();
		$imagick->readImageFile($photo['photo']);

		// Fix orientation, remove exif data
		apply_image_orientation($imagick);
		strip_exif_data($imagick);

		// Crop to square
		if ($format == self::FORMAT_SQUARE)
		{
			$y = intval(0.05 * $imagick->getImageHeight()); // Approximate location of face in PhotoCee portraits
			$size = min($imagick->getImageWidth(), $imagick->getImageHeight());

			if ($y + $size > $imagick->getImageHeight())
				$y = 0;

			$imagick->cropImage($size, $size, 0, $y);
		}

		// Scale to target width
		$imagick->scaleImage($width, 0);

		// Write the image as a progressive JPEG
		$imagick->setImageFormat('jpeg');
		$imagick->setInterlaceScheme(\Imagick::INTERLACE_PLANE);

		return $imagick->getImageBlob();
	}

	protected function _generate_placeholder(\DataIterMember $member, string $format, int $width, bool $private = false)
	{
		// Determine text
		if ($private)
			$text = '?';
		else
			$text = mb_strtoupper(sprintf('%s%s',
				mb_substr(trim($member->get('voornaam')), 0, 1),
				mb_substr(trim($member->get('achternaam')), 0, 1)
			));

		// Determine dimensions
		switch ($format)
		{
			case self::FORMAT_SQUARE:
				$height = $width;
				break;

			case self::FORMAT_PORTRAIT:
			default:
				$height = 1.5 * $width;
				break;
		}

		$imagick = new \Imagick();
		$draw = new \ImagickDraw();

		// Get semi-random background colour. The magic numbers constrain the
		// colour to not be too light or dark.
		$hash = md5($member->get('voornaam') . $member->get('achternaam'));
		$random_r = hexdec(substr($hash, 0, 2));
		$random_g = hexdec(substr($hash, 2, 2));
		$random_b = hexdec(substr($hash, 4, 2));

		$s_r = 0.213 * $random_r;
		$s_g = 0.715 * $random_g;

		$random_b = max($random_b, (0.5 - ($s_r + $s_g)) / 0.072);

		$s_b = 0.072 * $random_b;

		// Apply colours
		$background = new \ImagickPixel(sprintf('#%02x%02x%02x', $random_r, $random_g, $random_b));
		$foreground = '#fff';

		// Create the background layer
		$imagick->newImage($width, $height, $background);

		// Create text layer
		$draw->setFillColor($foreground);
		$draw->setFont(realpath('public/fonts/FiraSans-Regular.ttf'));
		$draw->setFontSize($width / 2);
		$draw->setTextAntialias(true);

		$metrics = $imagick->queryFontMetrics($draw, $text);

		$imagick->annotateImage($draw,
			($width - $metrics['textWidth']) / 2, // x
			($width - $metrics['boundingBox']['y2']) / 2 + $metrics['boundingBox']['y2'], // y
			0, // angle
			$text
		);

		// Write thje image as a PNG
		$imagick->setImageFormat('png');

		return $imagick->getImageBlob();
	}

	/**
	 * Serve cached original version of the profile picture.
	 */
	protected function _serve_cached_original(\DataIterProfilePicture $iter)
	{
		$cache = $this->get_cache();

		$key = sprintf('%d_original', $iter->get_id(), $format, $width);

		// Return not modified if no changes since the client last checked
		$last_modified = gmdate(DATE_RFC1123, $iter->get_mtime());
		if (
			$cache->hasItem($key)
			&& isset($_SERVER['HTTP_IF_MODIFIED_SINCE'])
			&& $_SERVER['HTTP_IF_MODIFIED_SINCE'] == $last_modified
		)
			return $this->run_304_not_modified();

		// Get image and serve
		$image = $cache->get($key, function (ItemInterface $item) use ($iter, $format, $width): string {
			$item->tag(sprintf('member_%d_picture', $iter['member_id']));
			return $this->_generate_scaled($iter, $format, $width);
		});

		return $this->serve_image($image, $last_modified);
	}

	/**
	 * Serve cached scaled version of the profile picture.
	 */
	protected function _serve_cached_scaled(\DataIterProfilePicture $iter, string $format, int $width)
	{
		$cache = $this->get_cache();

		$key = sprintf('%d_%s_%d', $iter->get_id(), $format, $width);

		// Return not modified if no changes since the client last checked
		$last_modified = gmdate(DATE_RFC1123, $iter->get_mtime());
		if (
			$cache->hasItem($key)
			&& isset($_SERVER['HTTP_IF_MODIFIED_SINCE'])
			&& $_SERVER['HTTP_IF_MODIFIED_SINCE'] == $last_modified
		)
			return $this->run_304_not_modified();

		// Get image and serve
		$image = $cache->get($key, function (ItemInterface $item) use ($iter, $format, $width): string {
			$item->tag(sprintf('member_%d_picture', $iter['member_id']));
			return $this->_generate_scaled($iter, $format, $width);
		});

		return $this->serve_image($image, $last_modified);
	}

	/**
	 * Serve cached placeholder.
	 *
	 * This needs to overcome two challenges unique to the placeholder:
	 * 1. The placeholder needs to be updated when a member changes their name.
	 * 2. The client needs a last modified date, but that's not available in
	 *    this context as there's no upload date.
	 *
	 * To this end, we cache some metadata with a hash of the members name in
	 * the key. The metadata contains a last upated date, and the hash in the
	 * key helps us invalidate the cache on a name change.
	 */
	protected function _serve_cached_placeholder(\DataIterMember $member, string $format, int $width)
	{
		$cache = $this->get_cache();

		$private = $member->is_private('naam');
		$hash = md5(member_full_name($member, \IGNORE_PRIVACY));

		// Determine keys and tags
		$key = sprintf('placeholder_%s_%d_%s_%d', ($private ? 'private' : 'public'), $member->get_id(), $format, $width);
		$meta_key = sprintf('placeholder_meta_%d_%s', $member->get_id(), $hash);
		$tag = sprintf('member_%d_placeholder', $member->get_id());

		// Invalidate the cache on name change
		if (!$cache->hasItem($meta_key))
			$cache->invalidateTags([$tag]);

		// Cache last modified as metadata
		$last_modified = $cache->get($meta_key, function (ItemInterface $item) use ($tag): string {
			$item->tag($tag);
			return gmdate(DATE_RFC1123);
		});

		// Return not modified if no changes since the client last checked
		if (
			$cache->hasItem($key)
			&& isset($_SERVER['HTTP_IF_MODIFIED_SINCE'])
			&& $_SERVER['HTTP_IF_MODIFIED_SINCE'] == $last_modified
		)
			return $this->run_304_not_modified();

		// Get image and serve
		$image = $cache->get($key, function (ItemInterface $item) use ($tag, $member, $format, $width, $private): string {
			$item->tag($tag);
			return $this->_generate_placeholder($member, $format, $width, $private);
		});

		return $this->serve_image($image, $last_modified);
	}

	/**
	 * View a specific profile picture.
	 * This view obeys the read policy for Profile Pictures. This policy should
	 * mostly follow the member's photo privacy settings, but not for admins and
	 * the member themselve. Use of this view should be limited to situations in
	 * which the admin or member needs to see the photo. Refer to run_member for
	 * all other uses.
	 */
	public function run_read(\DataIter $iter)
	{
		if (!get_policy($this->model)->user_can_read($iter))
			throw new \UnauthorizedException('You are not allowed to see this profile picture.');

		$format = $this->get_format();
		$width = $this->get_width();

		if ($format == self::FORMAT_ORIGINAL)
			return $this->_serve_cached_original($iter);
		else
			return $this->_serve_cached_scaled($iter, $format, $width);
	}

	/**
	 * View a member's profile picture.
	 * This view obeys the member's photo privacy settings at all time, and is
	 * therefore the preferred way to display a profile picture.
	 */
	protected function run_member()
	{
		$member_id = $this->get_parameter('member_id');

		if (empty($member_id))
			return new \Exception('No member ID provided');

		$member = $this->member_model->get_iter($member_id); // Throws 404 if not exists

		$format = $this->get_format();
		$width = $this->get_width();

		if ($format == self::FORMAT_ORIGINAL) {
			if ($this->member_model->is_private($member, 'foto'))
				throw new \UnauthorizedException('Photo is private');
			if (!$member->get_profile_picture())
				return new \NotFoundException('Member has no photo');
			return $this->_serve_cached_original($member->get_profile_picture());
		} elseif ($this->member_model->is_private($member, 'foto') || !$member->get_profile_picture()) {
			return $this->_serve_cached_placeholder($member, $format, $width);
		} else {
			return $this->_serve_cached_scaled($member->get_profile_picture(), $format, $width);
		}
	}

	/**
	 * Replaces a member's profile picture, or creates one if none exist. Also
	 * clears cache for this member's pictures.
	 */
	public function run_create()
	{
		// new_iter sets member_id based on parameters
		$iter = $this->new_iter();

		// but it may not exist…
		$member = $iter->get_member();
		if (!$member)
			throw new \NotFoundException('Member not found.');

		if (!\get_policy($this->model)->user_can_create($iter))
			throw new \UnauthorizedException('You are not allowed to upload a profile picture.');

		$form = $this->createForm(ProfilePictureType::class, null, ['mapped' => false]);
		$form->handleRequest($this->get_request());

		if ($form->isSubmitted() && $form->isValid()) {
			$file = $form['photo']->getData();
			$this->get_cache()->invalidateTags([sprintf('member_%d_picture', $iter['member_id'])]);

			$fh = fopen($file->getPathname(), 'rb');

			if (!$fh)
				throw new \RuntimeException(__('The uploaded file could not be opened.'));

			$this->model->set_for_member($member, $fh);

			fclose($fh);

			$referrer = $this->get_parameter(
				'referrer',
				$this->generate_url('profile', ['view' => 'profile', 'lid' => $member['id']])
			);
			return $this->view->redirect($referrer);
		}

		return $this->view()->render_create($member, $form, false);
	}

	/**
	 * Render profile picture review page. Semantically, this is a list of
	 * profile pictures, and thus the index. But it doesn't need to exist for
	 * most people.
	 */
	public function run_index()
	{
		$iter = $this->model->new_iter(['reviewed' => false]);

		if (!get_policy($this->model)->user_can_review($iter))
			throw new \NotFoundException();

		return $this->view()->render('index.twig', [
			'unreviewed' => $this->model->find(['reviewed' => false]),
			'all' => $this->model->find(['created_on__gt' => new \DateTime('6 months ago')]),
		]);
	}

	public function get_delete_form(\DataIter $iter = null)
	{
		$builder = $this->createFormBuilder(['csrf_token_id' => 'profile_picture_delete_' . $iter->get_id()]);

		if ($iter['member_id'] != \get_identity()->get('id')) {
			$builder->add('reason', TextareaType::class, [
				'label' => __('Reason for deletion'),
				'required' => true,
				'help' => __('You’re deleting someone else’s profile picture. They’ll be notified, so tell them why you deleted it.'),
			]);
		}

		$builder->add('submit', SubmitType::class, ['label' => __('Delete'), 'color' => 'danger']);

		$form = $builder->getForm();
		$form->handleRequest($this->get_request());
		return $form;
	}

	/**
	 * Deletes a profile picture. Sends a message to the member if the action
	 * was performed by an admin.Also clears cache for this member's pictures.
	 */
	public function run_delete(\DataIter $iter)
	{
 		if (!\get_policy($this->model)->user_can_delete($iter))
			throw new \UnauthorizedException('You are not allowed to delete this ' . get_class($iter) . '.');

		$success = false;

		$form = $this->get_delete_form($iter);

		if ($form->isSubmitted() && $form->isValid()) {
			$this->model->delete($iter);
			$this->get_cache()->invalidateTags([sprintf('member_%d_picture', $iter['member_id'])]);

			if ($iter['member_id'] != \get_identity()->get('id')) {
				$member = $iter->get_member();
				$mail = parse_email_object("profile_picture_delete.txt", [
					'reason' => $form->get('reason')->getData(),
				]);
				$mail->send($iter->get_member()['email']);
				$_SESSION['alert'] = sprintf(
					__('%s has been notified their profile picture was deleted.'),
					$member->get_full_Name()
				);
			}
			return $this->view->redirect($this->generate_url('profile_pictures.list'));
		}

		return $this->view->render('confirm_delete.twig', ['iter' => $iter, 'form' => $form->createView()]);
	}

	/**
	 * Mark profile picture as reviewed
	 */
	public function run_review(\DataIter $iter)
	{
		if (!get_policy($this->model)->user_can_review($iter))
			throw new UnauthorizedException('You are not allowed to review this profile picture.');

		$form = $this->createFormBuilder(null, ['csrf_token_id' => 'profile_picture_review_' . $iter->get_id()])
			->add('submit', SubmitType::class, ['label' => 'Review'])
			->getForm();
		$form->handleRequest($this->get_request());

		if ($form->isSubmitted() && $form->isValid()) {
			$iter['reviewed'] = true;
			$this->model->update($iter);
		}

		return $this->view->redirect($this->generate_url('profile_pictures.list'));
	}
}
