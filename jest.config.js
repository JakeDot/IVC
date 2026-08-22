export default {
  preset: 'ts-jest',
  testEnvironment: 'node',
<<<<<<< HEAD
=======
  maxWorkers: 1,
  testTimeout: 30000,
>>>>>>> f79f4cf (local state jakedot@petar-vivo)
  roots: ['<rootDir>/tests'],
  testMatch: ['**/__tests__/**/*.ts', '**/?(*.)+(spec|test).ts'],
  collectCoverageFrom: [
    'src/**/*.ts',
    '!src/**/*.d.ts',
  ],
  coverageThreshold: {
    global: {
      branches: 90,
      functions: 90,
      lines: 90,
      statements: 90
    }
  }
};
