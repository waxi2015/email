<?php

namespace Waxis\Email;

class Email {

	public $params = null;

	public static function log ($data) {
		$data['created_at'] = date('Y-m-d H:i:s');
		\DB::table('emails')->insert($data);

		return \DB::getPdo()->lastInsertId();
	}

	public static function getUnsubscribeLink ($email, $domain) {
		if (substr($domain,0,4) != 'http') {
			$domain = 'http://' . $domain;
		}
		return $domain . '/unsubscribe/' . $email . '/' . md5($email . env('APP_KEY'));
	}

	public static function unsubscribe ($email) {
		\DB::table('unsubscribes')
			->insert([
				'email' => $email
			]);
	}

	public static function canSend ($email) {
		return !(bool) \DB::table('unsubscribes')
			->where('email', $email)
			->count();
	}

	public function send ($params) {
		$this->params = $params;

		$content = '';
		$subject = '';
		$layout = '';

		if ((!isset($params['message']) || !isset($params['message']) || !isset($params['layout'])) && isset($params['template'])) {
			$template = to_array(\DB::table('email_templates')->where('identifier', $params['template'])->first());

			$content = $template['content'];
			$subject = $template['subject'];
			$layout = $template['layout'];

			$this->params['layout'] = $layout;
		} else {
			$content = $params['message'];
			$subject = $params['subject'];
			$layout = $params['layout'];
		}

		$this->_replaceEmailVarsWithValues($content, $subject);
		$this->_formatEmailHTML($content);

		$tos = $this->_getTos();

		$i = 0;
		foreach ($tos as $email => $name) {
			if (!empty($email) && self::canSend($email)) {
				if (env('APP_ENV') != 'local' || (env('APP_ENV') == 'local' && $i < 1)) {

					$template = 'emails.layout';

					if (!empty($layout) && $layout != 'default') {
						$template = 'emails.layout-' . $layout;
					}

					try {
						\Mail::send($template, ['content' => $content, 'to' => ['name' => $name, 'email' => $email]], function ($message) use ($subject, $name, $email, $content) {
			
						    $message->subject($subject);
							$message->to($email, $name);

							$id = self::log([
								'email' => $email,
								'name' => $name,
								'subject' => $subject,
								'message' => $content,
							]);
						});
					} catch (\Exception $e) {
						$id = self::log([
							'email' => $email,
							'name' => $name,
							'subject' => $subject,
							'message' => $content,
							'error' => $e->getMessage(),
						]);
						return false;
					}
				}

				$i++;
			}
		}
	}

	public function sendOut ($params, $data, $form) {
		$this->params = $params;

		if (!$this->_isSkip()) {
			$this->send($params);
		}

		if ($this->_isSkip()) {
			unset($form->feedback['true']['message']);
		}

		$emails = getValue($params, 'emails', false);

		if (empty($emails)) {
			return true;
		}

		$this->_updateFormFeedback($form);

		return true;
	}

	protected function _updateFormFeedback (&$form) {
		$emails = json_decode(decode($this->params['emails']),true);

		if (!isset($form->feedback)) {
			$form->feedback = [
				'true' => [
					'valid' => true,
					'message' => 'cms.emails.send_success',
					'params' => [
						'emails' => $emails,
					],
					'after' => ['waxcms.confirmEmail']
				]
			];
		} else {
			if (!isset($form->feedback['true'])) {
				$form->feedback['true'] = [
					'message' => 'cms.emails.send_success'
				];
			}

			if (!isset($form->feedback['true']['params'])) {
				$form->feedback['true']['params'] = [];
			}

			if (!isset($form->feedback['true']['after'])) {
				$form->feedback['true']['after'] = [];
			}
		}

		$form->feedback['true']['after'][] = 'waxcms.confirmEmail';

		$form->feedback['true']['params']['emails'] = $emails;
		
		if ($this->_isSkip()) {
			unset($form->feedback['true']['message']);
		}
	}

	protected function _getTos () {
		if (!is_array($this->params['to'])) {
			return json_decode(decode($this->params['to']),true);
		}

		return $this->params['to'];
	}

	# Extends html tags with attributes required
	# for proper email formatatting (EDMDesigner rules)
	protected function _formatEmailHTML (&$html) {
		$dom = new \DOMDocument;
		$dom->loadHTML(utf8_decode($html), LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
		$ps = $dom->getElementsByTagName('p');
		$as = $dom->getElementsByTagName('a');
		
		foreach ($ps as $p) {
			$p->setAttribute('style', 'margin: 0; padding: 0;');
		}
		
		foreach ($as as $a) {
			$color = '#24a7d0';

			$a->setAttribute('style', "color: $color !important");
			$txt = $a->nodeValue;

			$font = $dom->createElement("font", $a->nodeValue);
			$font->setAttribute('style', "color: $color !important");

			$newA = $dom->createElement("a", '');
			$newA->setAttribute('href', $a->getAttribute('href'));
			$newA->setAttribute('target', $a->getAttribute('target'));
			$newA->setAttribute('style', "color: $color !important");

			$newA->appendChild($font);

			$a->parentNode->replaceChild($newA, $a);
		}

		$html = $dom->saveHTML();
	}

	# Replaces text {{variables}} with actual values
	protected function _replaceEmailVarsWithValues (&$content, &$subject) {
		if (getValue($this->params, 'params', false) !== false && !empty($this->params['params'])) {
			$emailParams = $this->params['params'];

			if (!is_array($emailParams)) {
				$emailParams = json_decode(decode($emailParams),true);
			}

			$this->_replaceVarsWithValues($content, $emailParams);
			$this->_replaceVarsWithValues($subject, $emailParams);
		}
	}

	protected function _replaceVarsWithValues (&$str, $params = []) {
		if(!empty($params) && preg_match_all('/{{+(.*?)}}/', $str, $matches)) {
			foreach ($matches[1] as $match) {
				if (isset($params[$match])) {
		    		$str = str_replace('{{'.$match.'}}', $params[$match], $str);
				}
			}
		}
	}

	protected function _isSkip () {
		$skip = getValue($this->params, 'skip', false);

		if ($skip !== false) {
			return true;
		}

		return false;
	}
}