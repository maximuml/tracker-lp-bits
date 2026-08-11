<?php

use App\Models\Setting;

class ATTACHMENT{
	public int $userid;
	public int $class;
	public int $countlimit;
	public int $countsofar=0;
	public int $sizelimit;
	/** @var array<int, string> */
	public array $allowedext = array();

	private function attachmentSetting(string $key, mixed $default = null): mixed
	{
		return Setting::get('attachment.' . $key, $default);
	}

	function __construct(int $userid) {
		$this->userid = $userid;
		$this->set_class();
		$this->set_count_so_far();
		$this->set_count_limit();
		$this->set_size_limit();
		$this->set_allowed_ext();
	}

	function enable_attachment(): bool
	{
		return $this->attachmentSetting('enableattach') == 'yes';
	}

	function set_class(): void
	{
		$userid = $this->userid;
		$row = \App\Support\UserDisplay::row($userid);
		$this->class = (int) ($row['class'] ?? 0);
	}

	function set_count_so_far(): void
	{
		$userid = $this->userid;
		$now = date("Y-m-d H:i:s", TIMENOW-86400);
		$countsofar = \Nexus\Database\NexusDB::table('attachments')
			->where('userid', $userid)
			->where('added', '>', $now)
			->count();
		$this->countsofar = (int) $countsofar;
	}

	function get_count_so_far(): int
	{
		return $this->countsofar;
	}

	function get_count_limit_class(int $class): int
	{
		$limits = [
			(int) $this->attachmentSetting('classfour', 0) => (int) $this->attachmentSetting('countfour', 0),
			(int) $this->attachmentSetting('classthree', 0) => (int) $this->attachmentSetting('countthree', 0),
			(int) $this->attachmentSetting('classtwo', 0) => (int) $this->attachmentSetting('counttwo', 0),
			(int) $this->attachmentSetting('classone', 0) => (int) $this->attachmentSetting('countone', 0),
		];
		krsort($limits);
		foreach ($limits as $classLimit => $countLimit) {
			if ($classLimit > 0 && $class >= $classLimit && $countLimit > 0) {
				return $countLimit;
			}
		}
		return 0;
	}

	function set_count_limit(): void
	{
		$class = $this->class;
		$countlimit = $this->get_count_limit_class($class);
		$this->countlimit = $countlimit;
	}

	function get_count_limit(): int
	{
		return $this->countlimit;
	}

	function get_count_left(): int
	{
		$left = $this->countlimit - $this->countsofar;
		return $left;
	}

	function get_size_limit_class(int $class): int
	{
		$limits = [
			(int) $this->attachmentSetting('classfour', 0) => (int) $this->attachmentSetting('sizefour', 0),
			(int) $this->attachmentSetting('classthree', 0) => (int) $this->attachmentSetting('sizethree', 0),
			(int) $this->attachmentSetting('classtwo', 0) => (int) $this->attachmentSetting('sizetwo', 0),
			(int) $this->attachmentSetting('classone', 0) => (int) $this->attachmentSetting('sizeone', 0),
		];
		krsort($limits);
		foreach ($limits as $classLimit => $sizeLimit) {
			if ($classLimit > 0 && $class >= $classLimit && $sizeLimit > 0) {
				return $sizeLimit;
			}
		}
		return 0;
	}

	function set_size_limit(): void
	{
		$class = $this->class;
		$sizelimit = $this->get_size_limit_class($class);
		$this->sizelimit = $sizelimit;
	}

	function get_size_limit_kb(): int
	{
		return $this->sizelimit;
	}

	function get_size_limit_byte(): int
	{
		return $this->sizelimit * 1024;
	}

	/**
	 * @return array<int, string>
	 */
	function get_allowed_ext_class(int $class): array
	{
		$tiers = [
			['class' => (int) $this->attachmentSetting('classone', 0), 'ext' => (string) $this->attachmentSetting('extone', '')],
			['class' => (int) $this->attachmentSetting('classtwo', 0), 'ext' => (string) $this->attachmentSetting('exttwo', '')],
			['class' => (int) $this->attachmentSetting('classthree', 0), 'ext' => (string) $this->attachmentSetting('extthree', '')],
			['class' => (int) $this->attachmentSetting('classfour', 0), 'ext' => (string) $this->attachmentSetting('extfour', '')],
		];

		$allowedext = array();
		foreach ($tiers as $tier) {
			if ($tier['class'] === 0 || $class >= $tier['class']) {
				$temprow = $this->extract_allowed_ext($tier['ext']);
				foreach ($temprow as $temp) {
					$allowedext[] = $temp;
				}
			} elseif ($tier['class'] > 0) {
				break;
			}
		}
		return $allowedext;
	}

	function set_allowed_ext(): void
	{
		$class = $this->class;
		$allowedext = $this->get_allowed_ext_class($class);
		$this->allowedext = $allowedext;
	}

	/**
	 * @return array<int, string>
	 */
	function get_allowed_ext(): array
	{
		return $this->allowedext;
	}

	/**
	 * @return array<int, string>
	 */
	function extract_allowed_ext(string $string): array
	{
		$string = rtrim(trim($string), ",");
		$exts = explode(",", $string);
		$extrow = array();
		foreach ($exts as $ext){
			$extrow[] = trim($ext);
		}
		return $extrow;
	}

	function is_gif_ani(string $filename): bool {
    		if(!($fh = @fopen($filename, 'rb')))
        		return false;
    		$count = 0;
	//an animated gif contains multiple "frames", with each frame having a
	//header made up of:
	// * a static 4-byte sequence (\x00\x21\xF9\x04)
	// * 4 variable bytes
	// * a static 2-byte sequence (\x00\x2C)

	// We read through the file til we reach the end of the file, or we've found
	// at least 2 frame headers
    		while(!feof($fh) && $count < 2){
        		$chunk = fread($fh, 1024 * 100); //read 100kb at a time
        		$count += preg_match_all('#\x00\x21\xF9\x04.{4}\x00\x2C#s', $chunk, $matches);
		}
    		return $count > 1;
	}
}
?>
