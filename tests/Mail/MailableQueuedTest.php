<?php

namespace Illuminate\Tests\Mail;

use Mockery as m;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use PHPUnit\Framework\TestCase;
use Illuminate\Container\Container;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Application;
use Illuminate\Mail\SendQueuedMailable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Filesystem\FilesystemManager;
use Illuminate\Support\Testing\Fakes\QueueFake;

class MailableQueuedTest extends TestCase
{
    public function tearDown()
    {
        m::close();
    }

    public function testQueuedMailableSent()
    {
        $queueFake = new QueueFake(new Application);
        $mailer = $this->getMockBuilder('Illuminate\Mail\Mailer')
            ->setConstructorArgs($this->getMocks())
            ->setMethods(['createMessage', 'to'])
            ->getMock();
        $mailer->setQueue($queueFake);
        $mailable = new MailableQueableStub();
        $queueFake->assertNothingPushed();
        $mailer->send($mailable);
        $queueFake->assertPushedOn(null, SendQueuedMailable::class);
    }

    public function testQueuedMailableWithAttachmentSent()
    {
        $queueFake = new QueueFake(new Application);
        $mailer = $this->getMockBuilder('Illuminate\Mail\Mailer')
            ->setConstructorArgs($this->getMocks())
            ->setMethods(['createMessage'])
            ->getMock();
        $mailer->setQueue($queueFake);
        $mailable = new MailableQueableStub();
        $attachmentOption = ['mime' => 'image/jpeg', 'as' => 'bar.jpg'];
        $mailable->attach('foo.jpg', $attachmentOption);
        $this->assertInternalType('array', $mailable->attachments);
        $this->assertCount(1, $mailable->attachments);
        $this->assertEquals($mailable->attachments[0]['options'], $attachmentOption);
        $queueFake->assertNothingPushed();
        $mailer->send($mailable);
        $queueFake->assertPushedOn(null, SendQueuedMailable::class);
    }

    public function testQueuedMailableWithAttachmentFromDiskSent()
    {
        $app = new Application;
        $container = Container::getInstance();
        $disk = $this->getMockBuilder(Filesystem::class)
            ->getMock();
        $filesystemFactory = $this->getMockBuilder(FilesystemManager::class)
            ->setConstructorArgs([$app])
            ->getMock();
        $container->singleton('filesystem', function () use ($filesystemFactory) {
            return $filesystemFactory;
        });
        $queueFake = new QueueFake($app);
        $mailer = $this->getMockBuilder('Illuminate\Mail\Mailer')
            ->setConstructorArgs($this->getMocks())
            ->setMethods(['createMessage'])
            ->getMock();
        $mailer->setQueue($queueFake);
        $mailable = new MailableQueableStub();
        $attachmentOption = ['mime' => 'image/jpeg', 'as' => 'bar.jpg'];

        $mailable->attachFromStorage('/', 'foo.jpg', $attachmentOption);

        $this->assertInternalType('array', $mailable->diskAttachments);
        $this->assertCount(1, $mailable->diskAttachments);
        $this->assertEquals($mailable->diskAttachments[0]['options'], $attachmentOption);

        $queueFake->assertNothingPushed();
        $mailer->send($mailable);
        $queueFake->assertPushedOn(null, SendQueuedMailable::class);
    }

    protected function getMocks()
    {
        return [m::mock('Illuminate\Contracts\View\Factory'), m::mock('Swift_Mailer')];
    }
}

class MailableQueableStub extends Mailable implements ShouldQueue
{
    use Queueable;

    public function build(): self
    {
        $this
            ->subject('lorem ipsum')
            ->html('foo bar baz')
            ->to('foo@example.tld');

        return $this;
    }
}
