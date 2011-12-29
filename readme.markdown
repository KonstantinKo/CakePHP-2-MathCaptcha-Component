# MathCaptcha Component for CakePHP 2. #

Generates a basic math equation with human phrasing to prevent automated spam.
It's still very much beatable by sophisticated spam bots but tends to prevent 99%
of a website's usual spam anyways.

Updates will be published on my [code blog](http://codefool.tumblr.com/) and/or the corresponding github repo.

Inspired by and partially based on [Jamie Nay's](https://github.com/jamienay/math_captcha_component) cakePHP 1.2 Math Captcha class.

Features:

+ differently phrased math problems

+ allows words and numbers as answer

+ built-in timer to devalidate answers that where too fast for a human

Version: 0.2.2

## Installation ##

Simply copy the MathCaptchaComponent into your app/Controller/Component directory.

Make sure to include `public $components = array('MathCaptcha');` into every Controller you want to use this from.

## Usage ##

In any controller that uses this component you can call
`$this->MathCaptcha->getCaptcha();`
and it will return a randomly generated and always differently phrased math problem.

By default the answer will automatically be saved as a session variable and 
can be checked in the next step with 
`$this->MathCaptcha->validate($data);`
This returns true if the answer is correct. By default the function allows loose
validation, so if the user typed "one" instead of "1" the answer will also be correct.

Finally you can also set some options when instantiating the component.
Do this by giving extra parameters in the $components, like this:

    public $components = array(
    	'MathCaptcha' => array(
    		'setting' => 'value',
    		'setting2' => 1));

Available settings with their defaults are:

+ `'timer' => 0` - Sets the seconds after which a correct answer becomes valid.
Generally automated bots will submit forms a lot faster than a human ever could.

+ `'godmode' => false` - This feature allows you to pass any captcha by answering "42".
This is useful for the development phase when you do manual testing.

For more advanced options please check the documentation inside the source code.


## Example ##

In the controller:

    class UsersController extends AppController
    {
    	public $name = 'Users';
    	public $components = array('MathCaptcha', array('timer' => 3));

    	public function add() {
    		$this->set('captcha', $this->MathCaptcha->getCaptcha());

        if ($this->request->is('post')) {
          $this->User->create();
          if ($this->MathCaptcha->validate($this->request->data['User']['captcha'])) {
            $this->User->save($this->request->data);
          } else {
            $this->Session->setFlash('The result of the calculation was incorrect. Please, try again.');
          }
        } 
      }
    }

And in the View:

    // Users/add.ctp
    echo $this->Form->create('User');
    # ...
    echo $this->Form->input('captcha', array('label' => 'Calculate this: '.$captcha));
    echo $this->Form->end('Submit');
