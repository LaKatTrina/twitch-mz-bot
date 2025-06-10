const Birthday = require('../handlers/birthday');

describe('Birthday handler', () => {
  const birthday = new Birthday();

  test('_getDay parses the day', () => {
    expect(birthday._getDay('03-08')).toBe(3);
  });

  test('_getMonth parses the month', () => {
    expect(birthday._getMonth('03-08')).toBe(8);
  });

  test('_getText formats correctly', () => {
    const model = { nick: 'foo', day: 3, month: 8 };
    expect(birthday._getText(model)).toBe('foo cumple el dia 3 de Agosto');
  });
});
