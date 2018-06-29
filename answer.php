<?php

class Answer
{
    /**
     * cookie值
     *
     * @var
     */
    protected $cookie;

    /**
     * 试卷列表
     *
     * @var array
     */
    protected $paperList = [];

    /**
     * 题目MD5集合
     *
     * @var array
     */
    protected $titleMd5List = [];

    /**
     * Uooc constructor.
     *
     * @param $cookie
     */
    public function __construct($cookie)
    {
        $this->cookie = $cookie;
    }

    /**
     * 添加试卷
     *
     * @param string $url
     */
    public function setPaper($url = '')
    {
        $this->paperList[] = $url;
    }


    public function handle()
    {
        $questionList = [];
        foreach ($this->paperList as $paperUrl) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_COOKIE, $this->cookie);
            curl_setopt($ch, CURLOPT_URL, $paperUrl);
            $res = curl_exec($ch);
            if (!$res) {
                throw new \Exception('获取试卷失败');
            }
            $res = json_decode($res, true);
            if (!isset($res['data']['questions'])) {
                throw new \Exception('获取试卷题目失败');
            }
            $questions = $res['data']['questions'];
            //var_dump($questions);
            foreach ($questions as $question) {
                $str   = '';
                $title = strip_tags($question['question']);
                $title = str_replace('&nbsp;', '', $title);
                $md5   = md5($title);
                if (in_array($md5, $this->titleMd5List)) {
                    continue;
                }
                $this->titleMd5List[] = $md5;
                $answer               = '';
                foreach ($question['answer'] as $answerKey) {
                    $answer .= ($question['options'][$answerKey]) . "<br/>";
                }
                $answer = strip_tags($answer);
                $str    .= $title . "<br/>" . $answer;
                $str    .= "<br/>---------------<br/><br/>";

                $questionList[$question['type']][] = $str;
            }
        }
        $str = ' ';
        foreach ($questionList as $item) {
            $str .= implode('<br/>', $item);
        }
        //echo $str;
        file_put_contents('./唐宋词与人生.txt', $str);
    }
}

$cookie = '';
$answer = new Answer($cookie);
$answer->setPaper('http://www.uooconline.com/exam/view?cid=1655354423&tid=2141292770');
$answer->setPaper('http://www.uooconline.com/exam/view?cid=1655354423&tid=435483521');
$answer->setPaper('http://www.uooconline.com/exam/view?cid=1655354423&tid=1569661383');
$answer->setPaper('http://www.uooconline.com/exam/view?cid=1655354423&tid=847038008');
$answer->setPaper('http://www.uooconline.com/exam/view?cid=1655354423&tid=271163165');
//$answer->setPaper('http://www.uooconline.com/exam/view?cid=1210229148&tid=1329227823');
//$answer->setPaper('http://www.uooconline.com/exam/view?cid=1210229148&tid=770113792');
//$answer->setPaper('http://www.uooconline.com/exam/view?cid=1210229148&tid=64267877');
//$answer->setPaper('http://www.uooconline.com/exam/view?cid=1210229148&tid=1635942214');
//$answer->setPaper('http://www.uooconline.com/exam/view?cid=1210229148&tid=1181685691');
$answer->handle();