const editor = grapesjs.init({
  container: '#editor',
  height: '100%',
  fromElement: false,
  width: 'auto',
  storageManager: {
    type: 'remote',
    stepsBeforeSave: 1,
    urlStore: 'save.php',
    contentTypeJson: true,
  },
  plugins: ['gjs-preset-newsletter'],
  pluginsOpts: {
    'gjs-preset-newsletter': {}
  }
});
