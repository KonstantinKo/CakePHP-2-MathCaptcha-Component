<?
/**
* MathCaptcha Component for CakePHP 2.
*
* Generates a basic math equation with human phrasing to prevent automated spam.
* It's still very much beatable by sophisticated spam bots but tends to prevent 99%
* of a website's usual spam anyways.
*
* Inspired by and partially based on Jamie Nay's cakePHP 1.2 Math Captcha class.
* -> https://github.com/jamienay/math_captcha_component
*
* Features:
* + differently phrased math problems
* + allows words and numbers as answer
* + built-in timer to devalidate answers that where too fast for a human
*
* @author Konstantin Koss
* @version 0.2.2
* @copyright Copyright 2011, Konstantin Koss
* @license MIT License (http://www.opensource.org/licenses/mit-license.php)
* @package app.Controller.Component
* @link http://codefool.tumblr.com/
* @filesource
*/
class MathCaptchaComponent extends Component {

  /**
  * Other components needed by this component: Sessions.
  *
  * @access public
  * @var array
  */
  public $components = array('Session');

  /**
  * Phrasing-choices for the captcha question.
  *
  * @internal Feel free to add your own, available .:placeholders:. below.
  *
  * @access private
  * @var array
  */
  private $choices = array(
    "What is the sum of .:addition-problem:.?", 
    "What does .:word-problem:. result in?", 
    "What is .:problem:.?", 
    "What's the result of .:word-problem:.?", 
    "What do you get when you .:word2-problem:.?" 
  ); 

  /**
  * Placeholders to be used in the question phrasing. Recursion enabled.
  * 
  * @internal 
  *   If an array is given as the first level value, a random element will be
  *   chosen out of it to replace it.
  *   2nd level values should be numbers, operators ("+", "-", "*", "/"), or
  *   a boolean false. In order to create a valid equation, two numbers and one 
  *   operator have to exist in any given captcha question.
  *
  *   Careful with subtraction or division as the order in which the numbers 
  *   are mentioned matter. Generally it tends to be too difficult for users 
  *   to not drain conversion rates.
  *
  * @access private
  * @var array
  */
  private $placeholders = array(
    ".:problem:." => ".:number:. .:operator:. .:number:.",
    ".:word-problem:." => array(
      ".:operatorword-ing:. .:number:." => false,
      ".:number:. .:operator:. .:number:." => false),

    ".:word2-problem:." => ".:operatorword:. .:number:.",
    ".:addition-problem:." => ".:number:. .:add:. .:number:.",


    ".:number:." => array(
      "0" => 0,
      "zero" => 0,
      "1" => 1,
      "one" => 1,
      "2" => 2,
      "two" => 2,
      "3" => 3,
      "three" => 3,
      "4" => 4,
      "four" => 4,
      "5" => 5,
      "five" => 5),

    ".:operator:." => array(
      "+" => "+",
      "plus" => "+",
      "added to" => "+",
      "times" => "*",
      "multiplied by" => "*"),
    
    ".:add:." => array(
      "and" => "+",
      "plus" => "+",
      "+" => "+"),

    ".:operatorword:." => array( 
      "add .:number:. to" => "+",
      "multiply .:number:. by" => "*"),

    ".:operatorword-ing:." => array(
      "adding .:number:. to" => "+",
      "multiplying .:number:. by" => "*")
  );

  /**
  * Answer alternatives to enable the user to reply in words OR numbers.
  *
  * @internal When modifying placeholders, make sure every possible result is
  *   covered in this property. To give more than one alternative make an array
  *   with alternative strings, for example to compensate for typos.
  *
  * @access private
  * @var array
  */
  private $alternatives = array(
    0  => array("zero", "O", "null", "nil", "nada", "zip", "zilch", "nothing", "rien", "naught"),
    1  => "one",
    2  => "two",
    3  => "three",
    4  => "four",
    5  => "five",
    6  => "six",
    7  => "seven",
    8  => "eight",
    9  => "nine",
    10 => "ten",
    12 => "twelve",
    15 => array("fifteen", "fivteen"),
    16 => "sixteen",
    20 => "twenty",
    25 => array("twentyfive", "twenty-five")
  );

  /**
  * The Captcha-Question.
  *
  * @access private
  * @var string
  */
  private $question;

  /**
  * The Operator of the equation.
  *
  * @access private
  * @var string
  */
  private $operator;

  /**
  * The first number in the equation.
  *
  * @access private
  * @var integer
  */
  private $firstnum;

  /**
  * The second number in the equation.
  *
  * @access private
  * @var integer
  */
  private $secondnum;

  /**
  * Default values for settings.
  *
  * @access private
  * @var array
  */
  private $__defaults = array(
    'timer' => 0,
    'godmode' => false,
    'tabsafe' => false
  );

  /**
  * Constructor method.
  *
  * Set a 'timer' in seconds to allow correct captchas only after a
  * certain amount of time, for example ['timer' => 3] for 3 seconds. (This
  * denies access to spammers even if they can crack your captcha while human
  * users probably won't notice it at all.)
  *
  * Setting 'godmode' to true is useful for development and will enable you to
  * pass all captcha validations by typing "42" into the captcha input.
  *
  * If 'tabsafe' is set to true this component won't use session-based validation.
  * This means a little more work for you as you will have to call getResult()
  * in your controller, put the md5-digested answer in a hidden field on your form,
  * and call validate() with the first param being an array containing the
  * user's answer and the correct answer from your hidden field.
  *
  * @access public
  * @param ComponentCollection $collection
  * @param array $settings
  *
  */
  public function __construct($collection, $settings = array()) {
    parent::__construct($collection, $settings);
    $this->settings = array_merge($this->__defaults, $settings);
  } 

  /**
  * Resets previously generated MathCaptcha.
  *
  * If none existed it won't do anything.
  *
  * @access public
  * @return void
  */
  public function reset() {
    $this->question = null;
    $this->operator = null;
    $this->firstnum = null;
    $this->secondnum = null;
  }

  /**
  * Initiates the creation of a new MathCaptcha.
  *
  * @access public
  * @param bool $autoRegister Defaults to true. Determines whether or not to
  *             automatically register the correct answer in the user's session.
  * @return string Returns the created CAPTCHA question
  */
  public function makeCaptcha($autoRegister = true) {
    $this->reset();
    $question = $this->choices[mt_rand(0, count($this->choices)-1)];
    $this->convertPlaceholders($question);

    if ($autoRegister && !$this->settings['tabsafe']) $this->registerAnswer();

    return $this->question;
  }

  /**
  * Recursive function for placeholder replacement.
  *
  * Runs until two numbers and an operator where filled in.
  * Use maxrepeat when memory errors occur and repeat the function manually.
  *
  * @access private
  * @param string $string The string with .:placeholders:. to replace.
  * @param integer $maxrepeat Maximum amount of recursions. -1 for unlimited.
  * @param integer $repitition Current Repititon. Do not set manually.
  */
  private function convertPlaceholders($string, $maxrepeat = -1, $repitition = 0) {
    foreach ($this->placeholders as $key => $value) {

      $pos = strpos($string, $key);
      if ($pos !== false) {

        if (is_array($value)) {
          $replace = array_rand($value); // if value is array, gets random key out of it
          $string = substr_replace($string, $replace, $pos, strlen($key)); 

          if ($this->setResultParams($value[$replace])) { // saves operators/numbers
            $this->question = $string;
            return;
          }
          
        } else { // $value is string
          $replace = $value;
          $string = substr_replace($string, $replace, $pos, strlen($key));
        }
      }
    }
    unset($value); // clears memory used by foreach

    // recursive function
    if ($maxrepeat < 0)
      $this->convertPlaceholders($string);
    else if ($maxrepeat >= 0 && $repitition < $maxrepeat)
      $this->convertPlaceholders($string, $maxrepeat, $repitition+1); 
  }

  /**
  * Sets the parameters necessary for calculating the result.
  *
  * @access private
  * @return bool Returns true when all parameters have been filled, otherwise false.
  */
  private function setResultParams($value) {
    if (is_string($value)) { 
      $this->operator = $value;
    } else if (is_numeric($value)) {
      if ($this->firstnum === null) {
        $this->firstnum = $value;
      } else {
        $this->secondnum = $value;
        return true;
      }
    }
    return false;
  }

  /**
  * Returns the result of the current MathCaptcha.
  *
  * @access public
  * @throws ErrorException "MathCaptcha generation failed" gets thrown in case
  *   the function gets called before all numbers and operators have been set.
  * @return int
  */
  public function getResult() {
    if ($this->firstnum === null|| $this->secondnum === null || $this->operator === null) 
      throw new ErrorException("MathCaptcha generation failed.");

    $result = null;
    switch ($this->operator) {
      case "+":
        $result = $this->firstnum + $this->secondnum;
        break;
      case "-":
        $result = $this->firstnum - $this->secondnum;
        break;
      case "*":
        $result = $this->firstnum * $this->secondnum;
        break;
      case "/":
        $result = $this->firstnum / $this->secondnum;
    }

    if ($this->settings['tabsafe'])
      return md5($result);
    else
      return $result;
  }

  /**
  * Returns the current captcha question string.
  *
  * Creates a new one and returns it if none exist.
  *
  * @access public
  * @return string The MathCaptcha Question.
  */
  public function getCaptcha() {
    if ($this->question)
      return $this->question;
    else {
      return $this->makeCaptcha();
    }
  }

 /**
  * Save Answer to Session.
  *
  * Registers the answer to the math problem and the timer (if applicable) as 
  * session variables.
  *
  * @access public
  * @return integer
  */
  public function registerAnswer() {
    $answer = $this->getResult();

    $this->Session->write('MathCaptcha.result', $answer);
    if ($this->settings['timer'] && !$this->Session->read('MathCaptcha.time'))
      $this->Session->write('MathCaptcha.time', time());

    return $answer;
  }

  /**
  * Deletes all set session variables of the MathCaptcha Component.
  *
  * This is useful to be able to restart the timer or to declutter the session.
  *
  * @access public
  * @return void
  */
  public function unsetAnswer() {
    $this->Session->delete('MathCaptcha.result');
    $this->Session->delete('MathCaptcha.time');
  }
  
  /**
  * MathCaptcha Validation
  *
  * Compares the given data to the registered equation answer and compensates if
  * the user typed in the number as a word.
  *
  * @access public
  * @param mixed $data The data that gets validated. When using tabsafe, give an array
  *   with [$user_answer, $resulthash]. Otherwise just the user's answer as integer or string.
  * @param bool $loose Whether or not to allow corresponding words as correct answers.
  * @param bool $autoUnset Automatically removes the Session vars if the validation ends up true.
  * @return bool
  */
  public function validate($data, $loose = true, $autoUnset = true) {
    if (is_array($data)) 
      $answer = $data[0];
    else
      $answer = $data;

    if ($this->settings['godmode'] && $answer == 42)
      return true;

    if ($this->settings['timer']) {
      if (($this->Session->read('MathCaptcha.time') + $this->settings['timer']) > time())
        return false;
    }

    if ($this->settings['tabsafe'])
      return $this->validateTabsafe($data, $loose, $autoUnset);
    else
      $result = $this->Session->read('MathCaptcha.result');

    $validated = ($data == $result);

    if ($loose && !$validated) {
      if (is_array($this->alternatives[$result])) {
        foreach ($this->alternatives[$result] as $alternative) {
          if (strcasecmp($data, $alternative) == 0) $validated = true;
        }
      } else {
        if (strcasecmp($data, $this->alternatives[$result]) == 0) $validated = true;
      }
    }

    if ($validated && $autoUnset) $this->unsetAnswer();
    return $validated;
  }

  /**
  * Tabsafe MathCaptcha Validation
  *
  * @ignore
  */
  private function validateTabsafe($data, $loose, $autoUnset) {
    $result = $data[1];
    $data = $data[0];

    $validated = (md5($data) == $result);

    if ($loose && !$validated) {
      foreach ($this->alternatives as $key => $value) {
        if ($result == md5($key)) {
          if (is_array($value)) {
            foreach ($value as $alternative) {
              if (strcasecmp(md5($data), md5($alternative)) == 0) $validated = true;
            }
          } else {
            if (strcasecmp(md5($data), md5($value)) == 0) $validated = true;
          }
        }
      }
    }

    if ($validated && $autoUnset) $this->unsetAnswer();
    return $validated;
  }
}
?>