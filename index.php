<?php

class Uooc
{
    /**
     * 课程ID
     *
     * @var
     */
    protected $cid;

    /**
     * cookie值
     *
     * @var
     */
    protected $cookie;

    /**
     * 获取课程目录URL
     *
     * @var string
     */
    protected $getCatalogListUrl = 'http://www.uooconline.com/home/learn/getCatalogList';

    /**
     * 获取章节目录URL
     *
     * @var string
     */
    protected $getUnitLearnUrl = 'http://www.uooconline.com/home/learn/getUnitLearn';

    /**
     * 更新课程进度URL
     *
     * @var string
     */
    protected $markVideoLearnUrl = 'http://www.uooconline.com/home/learn/markVideoLearn';

    /**
     * MP4文件每秒大小
     *
     * @var int
     */
    protected $mp4SecondSize = 140000;

    /**
     * 获取的视频文件最长长度
     *
     * @var int
     */
    protected $maxLength = 0;

    /**
     * Uooc constructor.
     *
     * @param $cid
     * @param $cookie
     */
    public function __construct($cid, $cookie)
    {
        $this->cid    = $cid;
        $this->cookie = $cookie;
    }


    public function handle($courseName = '')
    {
        $courseNameLength = mb_strlen($courseName);
        if (!$courseNameLength) {
            echo '请输入课程名称' . PHP_EOL;
            exit;
        }
        $allUnit       = false;
        $chapterParent = null;
        $sectionList   = [];
        $catalogList   = $this->getCatalogList();
        //var_dump($catalogList);
        //exit;
        // 遍历目录
        foreach ($catalogList as $catItem) {
            //var_dump($catItem['name']);
            if (empty($catItem['children'])) {
                continue;
            }
            // 遍历章节
            foreach ($catItem['children'] as $chapter) {
                //var_dump($chapter['name']);
                if (mb_substr($chapter['name'], -$courseNameLength) === $courseName) {
                    $allUnit       = true;
                    $chapterParent = $chapter;
                }
                if (!empty($chapter['children'])) {
                    foreach ($chapter['children'] as $sectionItem) {
                        //var_dump($sectionItem['name']);
                        if ($allUnit) {
                            $sectionList[] = $sectionItem;
                        } elseif (mb_substr($sectionItem['name'], -$courseNameLength) === $courseName) {
                            $sectionList[] = $sectionItem;
                            $chapterParent = $chapter;
                            // 不是拿全部章节，只拿章节里面的一个小章，退出所有遍历
                            break 3;
                        }
                    }
                }
                // 拿了某一章的所有章节，退出所有遍历
                if ($allUnit) {
                    break 2;
                }
            }
        }
        if (is_null($chapterParent)) {
            echo '无匹配课程' . PHP_EOL;
            exit;
        }

        $videoList = $this->getSectionVideo($chapterParent, $sectionList);

        $maxLength = $this->maxLength + 100;
        for ($i = 0; $i <= $maxLength; $i += 10) {
            foreach ($videoList as $key => $item) {
                if ($item['finished'] == 0) {
                    $res = $this->markVideoLearn($item['chapter_id'], $item['section_id'], $item['subsection_id'],
                        $item['resource_id'], $item['video_length'], $i);
                    if (isset($res['data']['finished'])) {
                        $videoList[$key]['finished'] = $res['data']['finished'];
                    }
                    echo $item['title'] . ' ' . $item['video_length'] . ' ' . $i . ' ' . $maxLength . PHP_EOL;
                    echo json_encode($res) . PHP_EOL;
                }
            }
            echo '==============' . PHP_EOL;
            sleep(5);
        }
    }

    /**
     * 获取课程mp4地址列表
     *
     * @param array $chapterParent 章节信息
     * @param array $sectionList   课程列表
     *
     * @return array
     */
    protected function getSectionVideo($chapterParent, $sectionList)
    {
        //var_dump($sectionParent);
        //var_dump($sectionList);
        echo '共需学习 ' . (count($sectionList) + 1) . ' 个课程' . PHP_EOL;
        $videoList = [];
        $unitList  = [];
        unset($chapterParent['children']);
        $unitList[] = [
            'info' => $chapterParent,
            'list' => $this->getUnitLearn($chapterParent['pid'], $chapterParent['id']),
        ];
        foreach ($sectionList as $sectionItem) {
            //echo $sectionItem['name'] . PHP_EOL;
            $unitList[] = [
                'info' => $sectionItem,
                'list' => $this->getUnitLearn($chapterParent['pid'], $chapterParent['id'], $sectionItem['id']),
            ];
        }
        //var_dump($unitList);

        foreach ($unitList as $unitItem) {
            foreach ($unitItem['list'] as $item) {
                if ($item['type'] == 10) {
                    if (isset($item['video_url']['cdn1']['source'])) {
                        $videoList[] = [
                            'title'         => $item['title'],
                            'chapter_id'    => $chapterParent['pid'],
                            'resource_id'   => $item['id'],
                            'section_id'    => $chapterParent['id'],
                            'subsection_id' => $unitItem['info']['id'],
                            'video_url'     => $item['video_url']['cdn1']['source'],
                            'video_length'  => $this->getVideoLength($item['video_url']['cdn1']['source']),
                            'finished'      => 0,
                        ];
                    }
                }
            }
        }
        //var_dump($videoList);
        if (empty($videoList)) {
            echo '课程无需观看视频' . PHP_EOL;
            exit;
        }
        return $videoList;
    }


    /**
     * 获取课程目录
     *
     * @return mixed
     * @throws Exception
     */
    protected function getCatalogList()
    {
        $url = $this->getCatalogListUrl . '?' . http_build_query(['cid' => $this->cid]);
        $ch  = curl_init();
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_COOKIE, $this->cookie);
        curl_setopt($ch, CURLOPT_URL, $url);
        $res = curl_exec($ch);
        if (!$res) {
            throw new \Exception('获取课程目录失败');
        }
        //echo $res;
        $res     = json_decode($res, true);
        $catalog = $res['data'];
        return $catalog;
    }

    /**
     * 获取章节目录
     *
     * @param int $chapterId    章节父ID
     * @param int $sectionId    章节ID
     * @param int $subSectionId 章节ID
     *
     * @return mixed
     * @throws Exception
     */
    protected function getUnitLearn($chapterId, $sectionId, $subSectionId = 0)
    {
        $url = $this->getUnitLearnUrl . '?' . http_build_query([
                'cid'           => $this->cid,
                'chapter_id'    => $chapterId,
                'section_id'    => $sectionId,
                'subsection_id' => $subSectionId,
            ]);
        $ch  = curl_init();
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_COOKIE, $this->cookie);
        curl_setopt($ch, CURLOPT_URL, $url);
        $res = curl_exec($ch);
        if (!$res) {
            throw new \Exception('获取课程目录失败');
        }
        $res  = json_decode($res, true);
        $unit = $res['data'];
        return $unit;
    }

    /**
     * 粗略估计MP4文件长度
     *
     * @param string $videoUrl
     *
     * @return float
     */
    protected function getVideoLength($videoUrl)
    {
        $r = explode('/', $videoUrl);
        array_push($r, implode('%20', explode(' ', array_pop($r))));
        $header = get_headers(implode('/', $r), true);
        $length = $header['Content-Length'];
        $length = floor($length / $this->mp4SecondSize);
        echo $videoUrl . ' 估计长度 ' . $length . ' 秒' . PHP_EOL;
        if ($length > $this->maxLength) {
            $this->maxLength = $length;
        }
        return $length;
    }

    /**
     * 更新课程进度
     *
     * @param $chapterId
     * @param $sectionId
     * @param $subSectionId
     * @param $resourceId
     * @param $videoLength
     * @param $videoPos
     *
     * @return bool
     * @throws Exception
     */
    protected function markVideoLearn($chapterId, $sectionId, $subSectionId, $resourceId, $videoLength, $videoPos)
    {
        $post = [
            'chapter_id'    => $chapterId,
            'cid'           => $this->cid,
            'hidemsg_'      => true,
            'network'       => 1,
            'resource_id'   => $resourceId,
            'section_id'    => $sectionId,
            'source'        => 1,
            'subsection_id' => $subSectionId,
            'video_length'  => $videoLength,
            'video_pos'     => $videoPos,
        ];
        //var_dump($post);
        $ch = curl_init();
        //curl_setopt($ch, CURLOPT_HEADER, 1);                //启用时会将头文件的信息作为数据流输出
        //curl_setopt($ch, CURLOPT_NOBODY, 0);                //启用时是否不对HTML中的BODY部分进行输出
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        //curl_setopt($ch, CURLINFO_HEADER_OUT, 1);
        curl_setopt($ch, CURLOPT_COOKIE, $this->cookie);
        curl_setopt($ch, CURLOPT_URL, $this->markVideoLearnUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Origin: http://www.uooconline.com',
            'Accept-Encoding: gzip, deflate',
            'Accept-Language: zh-CN,zh;q=0.9,en;q=0.8,fr;q=0.7,ja;q=0.6,zh-TW;q=0.5',
            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/63.0.3239.132 Safari/537.36',
            'Content-Type: application/x-www-form-urlencoded;charset=UTF-8',
            'Accept: application/json, text/plain, */*',
            'Connection: keep-alive',
        ]);
        $res = curl_exec($ch);
        if (!$res) {
            throw new \Exception('更新课程进度失败');
        }
        //var_dump(curl_getinfo($ch));
        $res = json_decode($res, true);
        if ($res['code'] != 1) {
            echo $res['msg'] . PHP_EOL;
            return false;
        }
        return $res;
    }
}

$cookie = '';

// C语言课程ID
$cid        = '1676802997';
$courseName = '格式化输入scanf';
$uooc       = new Uooc($cid, $cookie);
$uooc->handle($courseName);

// 政治课程ID
$cid        = '2031162833';
$courseName = '新民主主义到社会主义的转变';
$uooc       = new Uooc($cid, $cookie);
$uooc->handle($courseName);

// 英语课程ID
$cid        = '856046843';
$courseName = 'Vocabulary';
$uooc       = new Uooc($cid, $cookie);
$uooc->handle($courseName);


echo round(memory_get_usage(true) / 1048576, 2) . ' MB' . PHP_EOL;