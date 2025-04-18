<?php
require_once('lib/TranslateController.php');

class TranslateTitlesExtension extends Minz_Extension {
    // 默认DeepLX API 地址
    private const ApiUrl = 'http://localhost:1188/translate';
    // 默认OpenAI API 地址
    private const OpenaiApiUrl = 'https://api.openai.com';

    public function init() {
        error_log('TranslateTitlesCN: Plugin initializing...');
        
        if (!extension_loaded('mbstring')) {
            error_log('TranslateTitlesCN 插件需要 PHP mbstring 扩展支持');
        }
        
        if (php_sapi_name() == 'cli') {
            // 确保 CLI 模式下有正确的用户上下文
            if (!FreshRSS_Context::$user_conf) {
                error_log('TranslateTitlesCN: No user context in CLI mode');
                // 可能需要手动初始化用户上下文
                $username = 'default'; // 或其他用户名
                FreshRSS_Context::$user_conf = new FreshRSS_UserConfiguration($username);
                FreshRSS_Context::$user_conf->load();
            }
        }
        
        $this->registerHook('feed_before_insert', array($this, 'addTranslationOption'));
        $this->registerHook('entry_before_insert', array($this, 'translateTitle'));

        if (is_null(FreshRSS_Context::$user_conf->TranslateService)) {
            FreshRSS_Context::$user_conf->TranslateService = 'google';
        }

        if (is_null(FreshRSS_Context::$user_conf->AiProvider)) {
            FreshRSS_Context::$user_conf->AiProvider = 'openai';
        }

        if (is_null(FreshRSS_Context::$user_conf->AiPrompt)) {
            FreshRSS_Context::$user_conf->AiPrompt = '你是一个专业的翻译助手，请将以下文本翻译成中文，只返回翻译结果，不要添加任何其他内容。';
        }

        if (is_null(FreshRSS_Context::$user_conf->DeeplxApiUrl)) {
            FreshRSS_Context::$user_conf->DeeplxApiUrl = self::ApiUrl;
        }

        if (is_null(FreshRSS_Context::$user_conf->LibreApiUrl)) {
            FreshRSS_Context::$user_conf->LibreApiUrl = 'http://localhost:5000';
        }

        if (is_null(FreshRSS_Context::$user_conf->LibreApiKey)) {
            FreshRSS_Context::$user_conf->LibreApiKey = '';
        }

        if (is_null(FreshRSS_Context::$user_conf->OpenaiApiUrl)) {
            FreshRSS_Context::$user_conf->OpenaiApiUrl = self::OpenaiApiUrl;
        }

        if (is_null(FreshRSS_Context::$user_conf->OpenaiApiKey)) {
            FreshRSS_Context::$user_conf->OpenaiApiKey = '';
        }

        if (is_null(FreshRSS_Context::$user_conf->OpenaiModel)) {
            FreshRSS_Context::$user_conf->OpenaiModel = 'gpt-3.5-turbo';
        }

        if (is_null(FreshRSS_Context::$user_conf->GeminiApiKey)) {
            FreshRSS_Context::$user_conf->GeminiApiKey = '';
        }

        if (is_null(FreshRSS_Context::$user_conf->GeminiModel)) {
            FreshRSS_Context::$user_conf->GeminiModel = 'gemini-pro';
        }

        if (is_null(FreshRSS_Context::$user_conf->GrokApiKey)) {
            FreshRSS_Context::$user_conf->GrokApiKey = '';
        }

        if (is_null(FreshRSS_Context::$user_conf->GrokModel)) {
            FreshRSS_Context::$user_conf->GrokModel = 'grok-1';
        }

        if (is_null(FreshRSS_Context::$user_conf->DeepseekApiKey)) {
            FreshRSS_Context::$user_conf->DeepseekApiKey = '';
        }

        if (is_null(FreshRSS_Context::$user_conf->DeepseekModel)) {
            FreshRSS_Context::$user_conf->DeepseekModel = 'deepseek-chat';
        }

        FreshRSS_Context::$user_conf->save();

        error_log('TranslateTitlesCN: Hooks registered');
        // error_log('TranslateTitlesCN: Current translation config: ' . json_encode(FreshRSS_Context::$user_conf->TranslateTitles));
    }

    public function handleConfigureAction() {
        // 处理配置请求
        if (Minz_Request::isPost()) {
            $translateService = Minz_Request::param('TranslateService', 'google');
            FreshRSS_Context::$user_conf->TranslateService = $translateService;
            
            $translateTitles = Minz_Request::param('TranslateTitles', array());
            error_log("TranslateTitlesCN: Saving translation config: " . json_encode($translateTitles));
            
            // 确保配置是数组形式
            if (!is_array($translateTitles)) {
                $translateTitles = array();
            }
            
            // 保存配置
            FreshRSS_Context::$user_conf->TranslateTitles = $translateTitles;
            
            $deeplxApiUrl = Minz_Request::param('DeeplxApiUrl', self::ApiUrl);
            FreshRSS_Context::$user_conf->DeeplxApiUrl = $deeplxApiUrl;

            $libreApiUrl = Minz_Request::param('LibreApiUrl', 'http://localhost:5000');
            FreshRSS_Context::$user_conf->LibreApiUrl = $libreApiUrl;

            $libreApiKey = Minz_Request::param('LibreApiKey', '');
            FreshRSS_Context::$user_conf->LibreApiKey = $libreApiKey;

            $aiProvider = Minz_Request::param('AiProvider', 'openai');
            FreshRSS_Context::$user_conf->AiProvider = $aiProvider;

            $aiPrompt = Minz_Request::param('AiPrompt', '');
            FreshRSS_Context::$user_conf->AiPrompt = $aiPrompt;

            $openaiApiUrl = Minz_Request::param('OpenaiApiUrl', self::OpenaiApiUrl);
            FreshRSS_Context::$user_conf->OpenaiApiUrl = $openaiApiUrl;

            $openaiApiKey = Minz_Request::param('OpenaiApiKey', '');
            FreshRSS_Context::$user_conf->OpenaiApiKey = $openaiApiKey;

            $openaiModel = Minz_Request::param('OpenaiModel', 'gpt-3.5-turbo');
            FreshRSS_Context::$user_conf->OpenaiModel = $openaiModel;

            $geminiApiKey = Minz_Request::param('GeminiApiKey', '');
            FreshRSS_Context::$user_conf->GeminiApiKey = $geminiApiKey;

            $geminiModel = Minz_Request::param('GeminiModel', 'gemini-pro');
            FreshRSS_Context::$user_conf->GeminiModel = $geminiModel;

            $grokApiKey = Minz_Request::param('GrokApiKey', '');
            FreshRSS_Context::$user_conf->GrokApiKey = $grokApiKey;

            $grokModel = Minz_Request::param('GrokModel', 'grok-1');
            FreshRSS_Context::$user_conf->GrokModel = $grokModel;

            $deepseekApiKey = Minz_Request::param('DeepseekApiKey', '');
            FreshRSS_Context::$user_conf->DeepseekApiKey = $deepseekApiKey;

            $deepseekModel = Minz_Request::param('DeepseekModel', 'deepseek-chat');
            FreshRSS_Context::$user_conf->DeepseekModel = $deepseekModel;

            // 保存并记录结果
            $saveResult = FreshRSS_Context::$user_conf->save();
            error_log("TranslateTitlesCN: Config save result: " . ($saveResult ? 'success' : 'failed'));
            
            // 保存后立即验证配置
            error_log("TranslateTitlesCN: Saved config verification: " . 
                json_encode(FreshRSS_Context::$user_conf->TranslateTitles));
        }
    }

    public function handleUninstallAction() {
        // 清除所有与插件相关的用户配置
        if (isset(FreshRSS_Context::$user_conf->TranslateService)) {
            unset(FreshRSS_Context::$user_conf->TranslateService);
        }
        if (isset(FreshRSS_Context::$user_conf->TranslateTitles)) {
            unset(FreshRSS_Context::$user_conf->TranslateTitles);
        }
        if (isset(FreshRSS_Context::$user_conf->DeeplxApiUrl)) {
            unset(FreshRSS_Context::$user_conf->DeeplxApiUrl);
        }
        if (isset(FreshRSS_Context::$user_conf->LibreApiUrl)) {
            unset(FreshRSS_Context::$user_conf->LibreApiUrl);
        }
        if (isset(FreshRSS_Context::$user_conf->LibreApiKey)) {
            unset(FreshRSS_Context::$user_conf->LibreApiKey);
        }
        if (isset(FreshRSS_Context::$user_conf->AiProvider)) {
            unset(FreshRSS_Context::$user_conf->AiProvider);
        }
        if (isset(FreshRSS_Context::$user_conf->AiPrompt)) {
            unset(FreshRSS_Context::$user_conf->AiPrompt);
        }
        if (isset(FreshRSS_Context::$user_conf->OpenaiApiUrl)) {
            unset(FreshRSS_Context::$user_conf->OpenaiApiUrl);
        }
        if (isset(FreshRSS_Context::$user_conf->OpenaiApiKey)) {
            unset(FreshRSS_Context::$user_conf->OpenaiApiKey);
        }
        if (isset(FreshRSS_Context::$user_conf->OpenaiModel)) {
            unset(FreshRSS_Context::$user_conf->OpenaiModel);
        }
        if (isset(FreshRSS_Context::$user_conf->GeminiApiKey)) {
            unset(FreshRSS_Context::$user_conf->GeminiApiKey);
        }
        if (isset(FreshRSS_Context::$user_conf->GeminiModel)) {
            unset(FreshRSS_Context::$user_conf->GeminiModel);
        }
        if (isset(FreshRSS_Context::$user_conf->GrokApiKey)) {
            unset(FreshRSS_Context::$user_conf->GrokApiKey);
        }
        if (isset(FreshRSS_Context::$user_conf->GrokModel)) {
            unset(FreshRSS_Context::$user_conf->GrokModel);
        }
        if (isset(FreshRSS_Context::$user_conf->DeepseekApiKey)) {
            unset(FreshRSS_Context::$user_conf->DeepseekApiKey);
        }
        if (isset(FreshRSS_Context::$user_conf->DeepseekModel)) {
            unset(FreshRSS_Context::$user_conf->DeepseekModel);
        }
        FreshRSS_Context::$user_conf->save();
    }

    public function translateTitle($entry) {
        // CLI 模式下的特殊处理
        if (php_sapi_name() == 'cli') {
            if (!FreshRSS_Context::$user_conf) {
                // 获取所有用户列表
                $usernames = $this->listUsers();
                foreach ($usernames as $username) {
                    // 初始化用户配置
                    FreshRSS_Context::$user_conf = new FreshRSS_UserConfiguration($username);
                    FreshRSS_Context::$user_conf->load();
                    break; // 只处理第一个用户
                }
            }
        }
        
        // 原有的翻译逻辑
        $feedId = $entry->feed()->id();
        if (isset(FreshRSS_Context::$user_conf->TranslateTitles[$feedId]) && 
            FreshRSS_Context::$user_conf->TranslateTitles[$feedId] == '1') {
            $title = $entry->title();
            error_log("Original title: " . $title);
            
            $translateController = new TranslateController();
            $translatedTitle = $translateController->translateTitle($title);
            
            error_log("Translated title: " . ($translatedTitle ?: 'translation failed'));
            
            if (!empty($translatedTitle)) {
                $entry->_title($translatedTitle . ' - ' . $title); // 将翻译后的标题放在前，原文标题放在后
            }
        }
        return $entry;
    }

    // 添加一个辅助函数来获取用户列表
    private function listUsers() {
        $path = DATA_PATH . '/users';
        $users = array();
        if ($handle = opendir($path)) {
            while (false !== ($entry = readdir($handle))) {
                if ($entry != "." && $entry != ".." && is_dir($path . '/' . $entry)) {
                    $users[] = $entry;
                }
            }
            closedir($handle);
        }
        return $users;
    }

    public function addTranslationOption($feed) {
        $feed->TranslateTitles = '0';
        return $feed;
    }

    public function handleTestAction() {
        header('Content-Type: application/json');
        
        $text = Minz_Request::param('test-text', '');
        if (empty($text)) {
            return $this->view->_error(404);
        }

        try {
            $serviceType = FreshRSS_Context::$user_conf->TranslateService ?? 'google';
            $translationService = new TranslationService($serviceType);
            $translatedText = $translationService->translate($text);

            if (!empty($translatedText)) {
                // 返回成功页面
                $this->view->_path('configure');
                $this->view->testResult = [
                    'success' => true,
                    'message' => $translatedText
                ];
            } else {
                $this->view->_path('configure');
                $this->view->testResult = [
                    'success' => false,
                    'message' => '翻译失败'
                ];
            }
        } catch (Exception $e) {
            $this->view->_path('configure');
            $this->view->testResult = [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
}
